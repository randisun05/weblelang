<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ItemStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Consignor;
use App\Models\Item;
use App\Models\ItemImage;
use App\Services\AuditLogger;
use App\Services\ImageService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ItemStatus::class)],
            'category' => ['nullable', 'integer'],
            'consignor' => ['nullable', 'integer'],
        ]);

        $items = Item::with('consignor:id,name,code', 'category:id,name', 'images')
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when($filters['consignor'] ?? null, fn ($q, $c) => $q->where('consignor_id', $c))
            ->latest()->paginate(20)->withQueryString()
            ->through(fn (Item $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'title' => $i->title,
                'image' => $i->images->first()?->url(),
                'consignor' => $i->consignor?->name,
                'category' => $i->category?->name,
                'reserve_price' => $i->reserve_price,
                'status' => Present::status($i->status),
                'received_at' => $i->received_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Items/Index', [
            'items' => $items,
            'filters' => $filters,
            'statuses' => ItemStatus::options(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'counts' => Item::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Items/Form', $this->formProps(null) + [
            'preselectConsignor' => $request->integer('consignor') ?: null,
        ]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $data = $this->validated($request);

        $item = new Item($data);
        $item->received_at ??= now();
        $item->save();

        $this->storeImages($request, $item, $images);
        AuditLogger::log('item.received', $item, ['consignor_id' => $item->consignor_id]);

        return redirect()->route('admin.items.show', $item)->with('success', "Barang {$item->code} diterima.");
    }

    public function show(Item $item): Response
    {
        $item->load('consignor', 'category', 'images', 'inspector:id,name', 'lots.auction:id,title,code');

        return Inertia::render('Admin/Items/Show', [
            'item' => [
                'id' => $item->id,
                'code' => $item->code,
                'title' => $item->title,
                'description' => $item->description,
                'condition' => Item::CONDITIONS[$item->condition] ?? $item->condition,
                'category' => $item->category?->name,
                'attributes' => Present::itemAttributes($item),
                'estimate_low' => $item->estimate_low,
                'estimate_high' => $item->estimate_high,
                'reserve_price' => $item->reserve_price,
                'commission_rate' => $item->effectiveCommissionRate(),
                'storage_location' => $item->storage_location,
                'received_at' => $item->received_at?->toIso8601String(),
                'inspection_notes' => $item->inspection_notes,
                'inspector' => $item->inspector?->name,
                'status' => Present::status($item->status),
                'editable' => $item->isEditable(),
                'transitions' => collect(Item::TRANSITIONS[$item->status->value] ?? [])
                    ->map(fn ($s) => Present::status(ItemStatus::from($s)))->values(),
                'consignor' => ['id' => $item->consignor->id, 'name' => $item->consignor->name, 'code' => $item->consignor->code],
                'images' => $item->images->map(fn ($img) => ['id' => $img->id, 'url' => $img->url()]),
                'lots' => $item->lots->map(fn ($lot) => [
                    'id' => $lot->id,
                    'auction' => $lot->auction->title,
                    'auction_id' => $lot->auction_id,
                    'lot_number' => $lot->lot_number,
                    'current_price' => $lot->current_price,
                    'bids_count' => $lot->bids_count,
                    'status' => Present::status($lot->status),
                ]),
            ],
        ]);
    }

    public function edit(Item $item): Response
    {
        abort_unless($item->isEditable(), 403, 'Barang yang sedang/sudah dilelang tidak dapat diubah.');
        $item->load('images');

        return Inertia::render('Admin/Items/Form', $this->formProps($item));
    }

    public function update(Request $request, Item $item, ImageService $images): RedirectResponse
    {
        abort_unless($item->isEditable(), 403, 'Barang yang sedang/sudah dilelang tidak dapat diubah.');

        $item->update($this->validated($request, $item));
        $this->storeImages($request, $item, $images);
        AuditLogger::log('item.updated', $item);

        return redirect()->route('admin.items.show', $item)->with('success', 'Data barang diperbarui.');
    }

    /** Memindahkan barang ke status berikutnya pada alur kerja. */
    public function transition(Request $request, Item $item): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ItemStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $to = ItemStatus::from($data['status']);

        if (! $item->canTransitionTo($to)) {
            return back()->with('error', "Tidak dapat mengubah status dari {$item->status->label()} ke {$to->label()}.");
        }

        if ($to === ItemStatus::Approved) {
            // Persetujuan untuk lelang hanya oleh admin; staf gudang cukup inspeksi.
            abort_unless($request->user()->hasRole(Role::SuperAdmin, Role::Admin), 403);

            if ($item->reserve_price <= 0 || $item->images()->doesntExist()) {
                return back()->with('error', 'Lengkapi harga limit dan minimal 1 foto sebelum menyetujui barang.');
            }
        }

        $item->status = $to;
        if ($to === ItemStatus::Inspected) {
            $item->inspected_by = $request->user()->id;
            $item->inspection_notes = $data['notes'] ?? $item->inspection_notes;
        }
        $item->save();

        AuditLogger::log('item.status', $item, ['to' => $to->value, 'notes' => $data['notes'] ?? null]);

        return back()->with('success', "Status barang: {$to->label()}.");
    }

    public function destroyImage(Item $item, ItemImage $image): RedirectResponse
    {
        abort_unless($image->item_id === $item->id && $item->isEditable(), 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Foto dihapus.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->lots()->exists()) {
            return back()->with('error', 'Barang yang pernah dilelang tidak dapat dihapus. Ubah status menjadi Dikembalikan.');
        }

        $item->delete();
        AuditLogger::log('item.deleted', $item);

        return redirect()->route('admin.items.index')->with('success', 'Barang dihapus.');
    }

    private function formProps(?Item $item): array
    {
        return [
            'item' => $item ? $item->only([
                'id', 'code', 'consignor_id', 'category_id', 'title', 'description', 'condition',
                'estimate_low', 'estimate_high', 'reserve_price', 'commission_rate', 'storage_location', 'inspection_notes',
            ]) + [
                'specs' => $item->specs ?: (object) [],
                'received_at' => $item->received_at?->format('Y-m-d'),
                'images' => $item->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url()]),
            ] : null,
            'consignors' => Consignor::orderBy('name')->get(['id', 'name', 'code', 'commission_rate']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'attribute_schema']),
            'conditions' => collect(Item::CONDITIONS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ];
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'consignor_id' => ['required', 'exists:consignors,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'condition' => ['required', Rule::in(array_keys(Item::CONDITIONS))],
            'specs' => ['nullable', 'array'],
            'estimate_low' => ['nullable', 'integer', 'min:0'],
            'estimate_high' => ['nullable', 'integer', 'gte:estimate_low'],
            'reserve_price' => ['required', 'integer', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'storage_location' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        // Validasi atribut dinamis sesuai skema kategori.
        $schema = Category::find($data['category_id'])?->attribute_schema ?? [];
        $attributes = [];
        $errors = [];
        foreach ($schema as $field) {
            $value = $data['specs'][$field['key']] ?? null;
            if (($field['required'] ?? false) && blank($value)) {
                $errors["specs.{$field['key']}"] = "{$field['label']} wajib diisi.";
            }
            if (($field['type'] ?? 'text') === 'number' && filled($value) && ! is_numeric($value)) {
                $errors["specs.{$field['key']}"] = "{$field['label']} harus berupa angka.";
            }
            if (($field['type'] ?? 'text') === 'select' && filled($value) && ! in_array($value, $field['options'] ?? [], true)) {
                $errors["specs.{$field['key']}"] = "{$field['label']} tidak valid.";
            }
            if (filled($value)) {
                $attributes[$field['key']] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $data['specs'] = $attributes;
        unset($data['images']);

        return $data;
    }

    private function storeImages(Request $request, Item $item, ImageService $images): void
    {
        $order = (int) $item->images()->max('sort_order');

        foreach ($request->file('images', []) as $file) {
            $item->images()->create([
                'path' => $images->store($file, 'items/'.$item->id),
                'sort_order' => ++$order,
            ]);
        }
    }
}
