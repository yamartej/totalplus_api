<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->get();
        return response()->json($products);
    }

    public function create(Request $request)
    {
        // Validar los datos recibidos del producto
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        // Crear el nuevo producto
        $product = Product::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
        ]);

        // Responder con el producto creado y el código de estado 201 (Recurso creado)
        return response()->json($product, 201);
    }

    public function get($id)
    {
        // Buscar el producto por su ID en la base de datos
        $product = Product::find($id);

        // Si el producto no existe, responder con el código de estado 404 (No encontrado)
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Responder con el producto y el código de estado 200 (OK)
        return response()->json($product, 200);
    }

    public function update(Request $request, $id)
    {
        // Buscar el producto por su ID en la base de datos
        $product = Product::find($id);

        // Si el producto no existe, responder con el código de estado 404 (No encontrado)
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Validar los datos recibidos para actualizar el producto
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        // Actualizar los datos del producto
        $product->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'image' => $request->input('image'),
            'category_id' => $request->input('category_id'),
        ]);

        // Responder con el producto actualizado y el código de estado 200 (OK)
        return response()->json($product, 200);
    }

    public function delete($id)
    {
        // Buscar el producto por su ID en la base de datos
        $product = Product::find($id);

        // Si el producto no existe, responder con el código de estado 404 (No encontrado)
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        // Eliminar el producto de la base de datos
        $product->delete();

        // Responder con el código de estado 204 (Sin contenido) ya que no hay respuesta para eliminar
        return response()->json(null, 204);
    }
}
