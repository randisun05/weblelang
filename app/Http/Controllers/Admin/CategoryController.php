<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validated($request));
        AuditLogger::log('category.created', $category);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        AuditLogger::log('category.updated', $category);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'Kategori yang sudah dipakai barang tidak dapat dihapus.');
        }

        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:16'],
            'attribute_schema' => ['nullable', 'array', 'max:30'],
            'attribute_schema.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'attribute_schema.*.label' => ['required', 'string', 'max:80'],
            'attribute_schema.*.type' => ['required', Rule::in(['text', 'number', 'select', 'textarea'])],
            'attribute_schema.*.options' => ['nullable', 'array'],
            'attribute_schema.*.options.*' => ['string', 'max:80'],
            'attribute_schema.*.required' => ['boolean'],
        ]);

        $data['slug'] = Str::slug($data['name']);
        if (Category::where('slug', $data['slug'])->whereKeyNot($category?->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Kategori dengan nama ini sudah ada.']);
        }
        $data['attribute_schema'] = array_values($data['attribute_schema'] ?? []);

        return $data;
    }
}
