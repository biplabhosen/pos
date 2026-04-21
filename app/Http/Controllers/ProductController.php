<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'products.page.' . $request->get('page', 1);

        $products = Cache::remember($cacheKey, 300, function () {
            return Product::with('category')->paginate(15);
        });

        return new ProductCollection($products);
    }

    public function salesReport(Request $request)
    {
        $orders = Order::with(['items.product', 'customer'])
            ->paginate(15);

        $report = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $report[] = [
                    'order_id'     => $order->id,
                    'product_name' => $item->product->name,
                    'qty'          => $item->quantity,
                    'total'        => $item->quantity * $item->product->price,
                    'customer'     => $order->customer->name,
                ];
            }
        }

        return response()->json([
            'data' => $report,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    public function dashboard()
    {
        return Cache::remember('dashboard.stats', 300, function () {
            $totalProducts = Product::count();
            $totalOrders = Order::count();
            $totalRevenue = Order::sum('total_amount');
            $categories = Category::all();

            $topProducts = Product::orderByDesc('sold_count')
                ->limit(5)
                ->get();

            return [
                'total_products' => $totalProducts,
                'total_orders'   => $totalOrders,
                'total_revenue'  => $totalRevenue,
                'categories'     => $categories,
                'top_products'   => $topProducts,
            ];
        });
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        $products = Product::where('name', 'LIKE', '%' . $keyword . '%')
                           ->orWhere('description', 'LIKE', '%' . $keyword . '%')
                           ->paginate(15);

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());

        Cache::forget('products.page.1');
        Cache::forget('dashboard.stats');

        return response()->json(new ProductResource($product), 201);
    }
}
