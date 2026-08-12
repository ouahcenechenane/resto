<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Section;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menuService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->menuService->getFullMenu($request->section_id));
    }

    public function bySection(string $code): JsonResponse
    {
        try {
            $section = Section::where('code', $code)->firstOrFail();
            $items = MenuItem::where('section_id', $section->id)
                ->with('category')
                ->where('is_available', 1)
                ->orderBy('order')
                ->get();
            return response()->json([
                'section' => $section,
                'items' => $items
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Section not found: ' . $e->getMessage()], 404);
        }
    }
public function fullMenu(Request $request): JsonResponse
{
    return response()->json($this->menuService->getFullMenu($request->section_id));
}

    public function storeItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'  => 'required|integer|exists:categories,id',
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string|max:500',
            'price'        => 'required|numeric|min:0',
            'image'        => 'nullable|string|max:500',
            'is_available' => 'boolean',
            'order'        => 'nullable|integer|min:0',
        ]);

        try {
            return response()->json($this->menuService->createItem($data), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function updateItem(Request $request, MenuItem $item): JsonResponse
    {
        $data = $request->validate([
            'category_id'  => 'sometimes|integer|exists:categories,id',
            'name'         => 'sometimes|string|max:150',
            'description'  => 'nullable|string|max:500',
            'price'        => 'sometimes|numeric|min:0',
            'image'        => 'nullable|string|max:500',
            'order'        => 'nullable|integer|min:0',
        ]);

        try {
            return response()->json($this->menuService->updateItem($item, $data));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function toggleItem(MenuItem $item): JsonResponse
    {
        return response()->json($this->menuService->toggleItem($item));
    }

    public function destroyItem(MenuItem $item): JsonResponse
    {
        try {
            $this->menuService->deleteItem($item);
            return response()->json(['message' => 'Article supprimé.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'name'       => 'required|string|max:100',
            'icon'       => 'nullable|string|max:50',
            'type'       => 'nullable|string|max:50',
            'order'      => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        return response()->json($this->menuService->createCategory($data), 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'icon'      => 'nullable|string|max:50',
            'order'     => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        return response()->json($this->menuService->updateCategory($category, $data));
    }

    public function destroyCategory(Category $category): JsonResponse
    {
        try {
            $this->menuService->deleteCategory($category);
            return response()->json(['message' => 'Catégorie supprimée.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $path = $request->file('image')->store('menu', 'public');
        $url  = asset('storage/' . $path);

        return response()->json(['url' => $url]);
    }
}