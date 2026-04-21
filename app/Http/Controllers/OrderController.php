<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items'       => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $totalAmount = 0;
                $createdItems = [];

                $order = Order::create([
                    'customer_id'  => $request->customer_id,
                    'total_amount' => 0,
                    'status'       => 'pending',
                ]);

                foreach ($request->items as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \Exception('Product unavailable or insufficient stock');
                    }

                    $orderItem = OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_price' => $product->price,
                    ]);

                    $product->decrement('stock', $item['quantity']);
                    $product->increment('sold_count', $item['quantity']);

                    $totalAmount += $product->price * $item['quantity'];
                    $createdItems[] = $orderItem;
                }

                $order->update(['total_amount' => $totalAmount]);

                $order->load(['customer', 'items.product']);

                Cache::forget('dashboard.stats');
                Cache::forget('products.page.1');

                return response()->json(new OrderResource($order), 201);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function index()
    {
        $orders = Order::with(['customer', 'items'])->paginate(15);

        return OrderResource::collection($orders);
    }

    public function filterByStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        $status = $request->input('status');

        $orders = Order::where('status', $status)
            ->with(['customer', 'items'])
            ->paginate(15);

        return OrderResource::collection($orders);
    }
}
