<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrado'], 404);
        }

        return response()->json($category, 200);
    }

    public function put(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Categoria no encontrado'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        return response()->json($category, 200);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrado'], 404);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
