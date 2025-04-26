<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Inventory;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'batches'])->get();
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
            'quantity' => $request->input('quantity'),
        ]);

        return response()->json([
            'product' => $product,
        ]);
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
            'quantity' => $request->input('quantity'),
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

    public function getAvailableProducts()
    {
        $products = Product::where('quantity', '>', 0)->get();
        foreach ($products as $product) {
            $totalQuantity = Inventory::where('product_id', $product->id)->sum('quantity');
            $currentStock = $product->quantity - $totalQuantity;
            $product->quantity = $currentStock;
        }

        return response()->json($products, 200);
    }

    public function updateBatchForProducts(Request $request)
    {
        // Validar los datos recibidos
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);
        // Actualizar el campo batch_id para los productos especificados
        Product::whereIn('id', $request->input('product_ids'))
            ->update(['batch_id' => $request->input('batch_id')]);

        // Obtener los productos actualizados para la respuesta
        $updatedProducts = Product::whereIn('id', $request->input('product_ids'))->get();

        // Responder con los productos actualizados
        return response()->json([
            'message' => 'Batch actualizado para los productos seleccionados.',
            'products' => $updatedProducts,
        ], 200);
    }
}
