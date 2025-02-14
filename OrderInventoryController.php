<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\OrderInventoryOrderItem;
use App\Models\Store;
use App\Models\OrderInventoryOrder;
use App\Models\ProductImage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\InventoryImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Models\User;
use Illuminate\Support\Carbon;

class OrderInventoryController extends Controller
{

    public function getProductsNotInOrderItems(): array
    {
        // Get all Product IDs from the Products table
        $productIds = Product::pluck('id')->toArray();

        // Get all Product IDs from the OrderInventoryOrderItems table
        $orderItemProductIds = OrderInventoryOrderItem::withoutGlobalScopes()->pluck('product_id')->toArray();

        // Find the difference
        $notInOrderItems = array_diff($productIds, $orderItemProductIds);

        return $notInOrderItems;
    }

    public function fetchOrderInventoryDataFromAPI(Request $request): JsonResponse
    {
        // Get the latest processed `vendor_order_id`
        $lastProcessedVendorOrder = OrderInventoryOrder::max('vendor_order_id') ?? 0;

        $isNonExistentProductEnabled = $request->query('nep', false); // Default to false if 'nep' is not provided
        $apiUrl = "https://{$apiDomain}/InventoryOrdersAPI.aspx?page=1&pageSize=5000&lastVendorOrderId={$lastProcessedVendorOrder}";

        try {
            $response = file_get_contents($apiUrl);

            if ($response === false) {
                throw new Exception("Failed to fetch data from API.");
            }

            $data = json_decode($response, true);

            // Process and format the data for display
            return $this->processOrderInventoryData($data, $isNonExistentProductEnabled);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function processOrderInventoryData(array $data, bool $isNonExistentProductEnabled): JsonResponse
    {
        $result = []; // Array to hold all processed orders
        $nonExistentProducts = []; // Array for missing products

        // Check if the response contains InventoryOrders
        if (!isset($data['InventoryOrders']) || empty($data['InventoryOrders'])) {
            return response()->json([
                'success' => false,
                'message' => 'No inventory orders found in API response.',
                'data' => $result // Return empty data
            ]);
        }

        // Decode the nested JSON string if necessary
        $inventoryOrders = is_string($data['InventoryOrders']) ? json_decode($data['InventoryOrders'], true) : $data['InventoryOrders'];

        // Group orders by Order_ID
        $groupedOrders = [];
        foreach ($inventoryOrders['InventoryOrders'] as $order) {
            $groupedOrders[$order['Order_ID']][] = $order;
        }

        // Process grouped orders
        foreach ($groupedOrders as $orderId => $orderItems) {
            $firstItem = $orderItems[0]; // Use the first item in the group for shared data

            preg_match('/\\/Date\((\d+)\)\\//i', $firstItem['TimePlaced'], $matches);
            $orderDate = date('Y-m-d H:i:s', substr($matches[1], 0, -3));

            if (isset($firstItem['PickupDate'])) {
                preg_match('/\\/Date\((\d+)\)\\//i', $firstItem['PickupDate'], $matchesPickup);
                $pickupDateTime = date('Y-m-d H:i:s', substr($matchesPickup[1], 0, -3));
            }

            // Calculate the subtotal
            $subTotal = array_reduce($orderItems, function ($total, $item) {
                return $total + ($item['Retail'] * $item['ItemQuantity']);
            }, 0);

            // Create the Order in `order_inventory_orders`
            $newResults = [
                'user_id' => $firstItem['User_ID'],
                'account_id' => 14, // Static account ID for r2o
                'sub_total' => $subTotal,
                'status' => $firstItem['Order_Status'] ?? 'Unknown',
                'comments' => $firstItem['Comments'] ?? null,
                'customer_name' => $firstItem['CustomerName'] ?? null,
                'is_pickup' => $firstItem['Pickup'] ? true : false,
                'pickup_by' => $firstItem['PickupBy'] ?? null,
                'pickup_date_timestamp' => $pickupDateTime ?? null,
                'order_notes' => $firstItem['Comments'] ?? null,
                'store_number' => $firstItem['Store_ID'],
                'vendor_order_id' => $firstItem['Order_ID'],
                'created_at' => $orderDate,
                'updated_at' => now()->format('Y-m-d H:i:s')
            ];

            $order = OrderInventoryOrder::create($newResults);
            $result[] = $newResults;

            // Process each product in the order
            foreach ($orderItems as $item) {
                $product = Product::withTrashed()->where('vendor_product_id', $item['Product_ID'])->first();

                if (!$product) {
                    // If product doesn't exist, store in $nonExistentProducts for later reference
                    $nonExistentProducts[] = [
                        'name' => $item['ProductName'],
                        'model_number' => $item['ModelNumber'],
                        'retail' => $item['Retail'],
                        'serial_number' => $item['SerialNumber'] ?? null,
                        'quantity' => $item['ItemQuantity'],
                        'created_at' => now()->format('Y-m-d H:i:s'),
                        'updated_at' => now()->format('Y-m-d H:i:s'),
                    ];
                } else {
                    // Insert product into `order_inventory_order_items`
                    OrderInventoryOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $item['ItemQuantity'],
                        'price' => $item['Retail'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Store order info in the result array
            $result[] = [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'store_number' => $order->store_number,
                'sub_total' => $order->sub_total,
                'status' => $order->status,
            ];
        }

        // Return missing products or imported orders depending on $isNonExistentProductEnabled
        if ($isNonExistentProductEnabled) {
            return response()->json([
                'success' => true,
                'message' => 'List of Non-Existing Products Created.',
                'data' => $nonExistentProducts
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Inventory orders processed successfully.',
                'data' => $result
            ]);
        }
    }

    public function exportCsvNow(Request $request)
    {
        $orders = $this->applyFilters($request)->get();

        // Prepare CSV headers
        $csvData = [
            ['Order ID', 'Date', 'Ordered By', 'Store Name', 'Customer Name', 'Quantity', 'Order Details', 'Status']
        ];

        foreach ($orders as $order) {
            $orderItems = $order->orderItems; // Get associated items

            if ($orderItems->isNotEmpty()) {
                foreach ($orderItems as $item) {
                    $csvData[] = [
                        'Order ID' => $order->id,
                        'Date' => $order->created_at,
                        'Ordered By' => optional($order->user)->first_name . ' ' . optional($order->user)->last_name,
                        'Store Name' => (intval($order->store_number) > 0
                            ? Auth::user()->allStores()->pluck('name', 'store_number')[$order->store_number] ?? 'N/A'
                            : 'N/A'),
                        'Customer Name' => $order->customer_name ?? '-',
                        'Quantity' => $item->quantity ?? 'N/A',
                        'Order Details' => ($item->product->name ?? 'N/A') . ($item->for_customer ? ' (Customer pick-up)' : ''),
                        'Status' => $order->status,
                    ];
                }
            } else {
                // If the order has no items, add a single row with "No Items"
                $csvData[] = [
                    'Order ID' => $order->id,
                    'Date' => $order->created_at->format('m/d/Y'),
                    'Ordered By' => optional($order->user)->first_name . ' ' . optional($order->user)->last_name,
                    'Store Name' => (intval($order->store_number) > 0
                        ? Auth::user()->allStores()->pluck('name', 'store_number')[$order->store_number] ?? 'N/A'
                        : 'N/A'),
                    'Customer Name' => $order->customer_name ?? 'N/A',
                    'Quantity' => 'N/A',
                    'Order Details' => 'No Items',
                    'Status' => $order->status,
                ];
            }
        }

        // Generate CSV file
        $csvFileName = 'Order-' . $order->id . '-' . date('m-d-Y', time()) . '--' . rand(1, 10000) . '.csv';
        $handle = fopen($csvFileName, 'w');

        foreach ($csvData as $line) {
            fputcsv($handle, $line);
        }

        fclose($handle);

        return response()->download($csvFileName)->deleteFileAfterSend(true);
    }


    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function saveComments(Request $request, $id): RedirectResponse
    {
        $order = OrderInventoryOrder::findOrFail($id);

        // Validate the comments input
        $request->validate([
            'comments' => 'nullable|string|max:2000',
        ]);

        // Update the comments column
        $order->update(['comments' => $request->input('comments')]);

        return redirect()->back()->with('success', 'Comments saved successfully.');
    }

    public function updateQuantity(Request $request, $orderId)
    {
        $validated = $request->validate([
            'key' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $orderItem = OrderInventoryOrderItem::findOrFail($validated['key']);
        $order = OrderInventoryOrder::findOrFail($orderId);
        $product = Product::find($orderItem->product_id);

        $originalQuantity = $orderItem->quantity;

        // Increase quantity in order
        if ($validated['quantity'] > $originalQuantity) {
            $differenceInQuantity = $validated['quantity'] - $originalQuantity;

            if (!$product || $product->quantity < $differenceInQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient quantity available.',
                ]);
            }

            $product->decrement('quantity', $differenceInQuantity);
        } // Reduce quantity in order
        else if ($validated['quantity'] < $originalQuantity) {
            $differenceInQuantity = $originalQuantity - $validated['quantity'];
            $product->increment('quantity', $differenceInQuantity);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No change in quantity.',
            ]);
        }

        // Update quantity in pivot table
        $orderItem->update([
            'quantity' => $validated['quantity'],
        ]);

        // Recalculate the subtotal
        $subTotal = $order->orderItems->sum(fn($item) => $item->quantity * $item->price);
        $order->update(['sub_total' => $subTotal]);

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully.',
        ]);
    }

    public function getProductQuantity($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ]);
        }

        return response()->json([
            'success' => true,
            'currentQuantity' => $product->quantity,
        ]);
    }


    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $order = OrderInventoryOrder::findOrFail($id);

        // Validate and set the status from query parameters
        $status = $request->query('status'); // Using query() for GET request
        if (!in_array($status, ['Requested', 'Completed', 'Canceled'])) {
            return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $order->status = $status;
        $order->save();

        if ($status == 'Canceled') {
            return $this->cancelOrder($id);
        }

        return response()->json(['success' => true, 'message' => 'Order status updated successfully!']);
    }

    /**
     * @param $id
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function printView($id): \Illuminate\Foundation\Application|View|Factory|Application
    {
        $order = OrderInventoryOrder::with([
            'user',
            'account',
            'orderItems.product'
        ])->findOrFail($id);

        return view('admin.order-inventory.print-view', compact('order'));
    }

    /**
     * @param Request $request
     * @return Builder
     */
    private function applyFilters(Request $request)
    {
        $query = OrderInventoryOrder::with([
            'account',
            'orderItems.product'
        ])
            ->whereHas('user', function ($q) {
                $q->whereColumn('users.id', 'order_inventory_orders.user_id')
                    ->orWhereColumn('users.remote_id', 'order_inventory_orders.user_id');   // Old legacy ID from import
            });

        if (Auth::user()->hasRole('admin') || Auth::user()->position_id == 56) {
            // show all store with no restrictions
        } else {
            $storeNumbers = array_keys(Auth::user()->myStoreByStoreNumber());
            $query->whereIn('store_number', $storeNumbers);
        }

        // Apply date range filter if specified
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_inventory_orders.created_at', [$request->start_date, $request->end_date]);
        }

        if ($request->get('pre_order') == 1) {
            $query->whereHas('orderItems.product', function ($query) {
                $query->where('preorder', 1);
            });
        }


        // Apply status filter if specified
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'Requested');
        }

        // Store filter
        if ($request->filled('store')) {
            $storeNumber = $request->input('store');
            $query->where('store_number', $storeNumber);
        }

        // Sorting
        return $query->select('order_inventory_orders.*')->orderBy('order_inventory_orders.created_at', 'desc');
    }

    /**
     * @param Request $request
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function viewOrders(Request $request)
    {
        $orders = $this->applyFilters($request)->paginate(25);

        return view('admin.order-inventory.orders', compact('orders'));
    }


    /**
     * @param Request $request
     * @return View|\Illuminate\Foundation\Application|Factory|Application
     */
    public function index(Request $request): View|\Illuminate\Foundation\Application|Factory|Application
    {
        // Get all categories for the dropdown filter
        $categories = Category::all();

        // Determine the view mode: default to 'grid' if not specified
        $viewMode = $request->input('view_mode', 'grid');

        // Build the product query with optional filters & user roles
        if (!Auth::user()->hasRole('admin')) {
            $query = Product::with('categories')->where('quantity', '>', 0);
        } else {
            $query = Product::with('categories');
        }

        if ($request->get('pre_order') == 1) {
            $query->where('preorder', 1);
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('search') . '%')
                    ->orWhere('short_description', 'like', '%' . $request->input('search') . '%')
                    ->orWhere('model_number', 'like', '%' . $request->input('search') . '%');
            });
        }

        // Combine category filters from multiple possible sources
        $categoryIds = [];

        if ($request->filled('prev_selected_categories_ids')) {
            $categoryIds = array_merge($categoryIds, explode(',', $request->input('prev_selected_categories_ids')));
        }

        $requestedCategories = $request->input('categories');
        if ($requestedCategories == "0") {
            $requestedCategories = [];
        } else if (!is_array($requestedCategories)) {
            $requestedCategories = [];
        }

        if ($request->filled('categories')) {
            $categoryIds = array_merge($categoryIds, $requestedCategories); // categories[] is already an array
        }

        // Remove duplicates (if needed)
        $categoryIds = array_unique($categoryIds);

        // Apply the filter only if there are category IDs
        if (!empty($categoryIds)) {
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Pagination settings
        $paginationCount = ($viewMode === 'grid') ? 24 : 25;
        $products = $query->paginate($paginationCount)->appends($request->query());


        // Attach Inventory Images to each product (fetch primary image only)
        foreach ($products as $product) {
            $primaryImage = $product->getImage();

            if ($primaryImage && $this->isValidImage($primaryImage)) {
                $product->primary_image = $primaryImage;
            } else {
                // Provide a default image if no valid primary image is found
                $product->primary_image = url('/img/default-placeholder.png');
            }
        }

        // Add category information to cart data in session
        $cart = session('order_inventory_cart', []);
        $productIds = collect($cart)->pluck('product_id');

        if ($productIds->isNotEmpty()) {
            // Fetch products with their categories
            $productsWithCategories = Product::with('categories')->whereIn('id', $productIds)->get();

            // Update the cart items with category data
            foreach ($cart as $key => &$item) {
                $product = $productsWithCategories->firstWhere('id', $item['product_id']);
                if ($product) {
                    $item['categories'] = $product->categories->pluck('name')->toArray();
                }
            }

            // Save the updated cart back to the session
            session(['cart' => $cart]);
        }

        $categoryIds = request('prev_selected_categories_ids', []);
        if (!is_array($categoryIds)) {
            $categoryIds = explode(',', $categoryIds);
        }

        return view('admin.order-inventory.index', compact('products', 'categories', 'viewMode', 'categoryIds'));
    }

    /**
     * Check if an image exists, is valid, and is larger than 1KB.
     *
     * @param string $imagePath
     * @return bool
     */
    private function isValidImage(string $imagePath): bool
    {
        if ($imagePath == "/") {
            return false;
        }

        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            // For remote images, use get_headers
            $headers = @get_headers($imagePath, 1);
            if (!$headers || strpos($headers[0], '200') === false) {
                return false;
            }

            // Optionally check Content-Type
            if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'image') === false) {
                return false;
            }

            // Check Content-Length (in bytes, > 1KB = 1024 bytes)
            if (isset($headers['Content-Length']) && $headers['Content-Length'] < 1024) {
                return false;
            }
        } else {
            // For local images, use file_exists
            $fullPath = public_path($imagePath);
            if (!file_exists($fullPath)) {
                return false;
            }

            // Check file size (in bytes, > 1KB = 1024 bytes)
            if (filesize($fullPath) < 1024) {
                return false;
            }
        }

        return true;
    }


    /**
     * @return JsonResponse
     */
    public function fetchAndStoreImagesFromAspApi()
    {
        $sourcePath = 'https://intranet.r2o.com/InventoryImages/';
        $destinationPath = 'inventory-images';

        // Ensure the destination directory exists
        Storage::makeDirectory($destinationPath);

        $apiUrl = 'http://intranet.r2o.com/InventoryProductImagesJson.aspx';
        $response = Http::get($apiUrl);

        if ($response->successful()) {
            $data = $response->json();
            $testCounter = 0; // Initialize the test counter

            foreach ($data['InventoryProductImages'] as $imageData) {
                // Break the loop if test limit is reached
                if ($testCounter >= 25) {
                    break;
                }

                // Skip rows where the filename is blank
                if (empty($imageData['ImageFileName'])) {
                    Log::info("Skipped record with blank filename for Product ID: {$imageData['Product_ID']}");
                    continue;
                }

                // Extract only the filename, ignoring any preceding directory paths
                $imageFileName = basename($imageData['ImageFileName']);

                // Save image data in the database
                InventoryImage::create([
                    'product_id' => $imageData['Product_ID'],
                    'image_filename' => $imageFileName,
                    'is_primary_image' => $imageData['IsPrimaryImage'],
                ]);

                // Define the full source and destination paths for each image
                $sourceImageUrl = $sourcePath . $imageFileName;
                $destinationImagePath = $destinationPath . '/' . $imageFileName;

                try {
                    // Try downloading the image using Http::get()
                    $imageContents = Http::timeout(10)->get($sourceImageUrl)->body();

                    // If image contents are not empty, process and save to storage
                    if (!empty($imageContents)) {
                        // Save the image to a temporary file
                        $tempPath = sys_get_temp_dir() . '/' . $imageFileName;
                        file_put_contents($tempPath, $imageContents);

                        // Check the file size
                        if (filesize($tempPath) > 2 * 1024 * 1024) { // 2MB limit
                            // Resize the image using Intervention Image
                            $resizedImage = \Intervention\Image\ImageManagerStatic::make($tempPath)
                                ->resize(1920, null, function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                })
                                ->encode();

                            // Save the resized image
                            Storage::put($destinationImagePath, $resizedImage->__toString());
                            Log::info("Resized and saved image: {$imageFileName}");
                        } else {
                            // Save the original image if under size limit
                            Storage::put($destinationImagePath, $imageContents);
                            Log::info("Downloaded image: {$imageFileName}");
                        }

                        // Remove the temporary file
                        unlink($tempPath);
                    } else {
                        throw new \Exception("Image content empty for URL: {$sourceImageUrl}");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to download image {$imageFileName}: " . $e->getMessage());

                    // Alternative download method using file_get_contents
                    try {
                        $imageContents = file_get_contents($sourceImageUrl);
                        if ($imageContents !== false) {
                            Storage::put($destinationImagePath, $imageContents);
                            Log::info("Downloaded image using alternative method: {$imageFileName}");
                        } else {
                            Log::error("Alternative download failed for image {$imageFileName}");
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed alternative download for image {$imageFileName}: " . $e->getMessage());
                    }
                }

                // Increment the test counter
                $testCounter++;
            }

            return response()->json(['message' => 'Data fetched and inserted successfully!'], 201);
        } else {
            return response()->json(['message' => 'Failed to fetch data from API'], 500);
        }
    }


    /**
     * @param $id
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function showProductDetail($id)
    {
        // Fetch the product by ID along with categories, brand, and images, including soft-deleted ones
        $product = Product::withTrashed()->with(['categories', 'brand'])->findOrFail($id);

        $primaryImage = $product->getImage();

        if ($primaryImage && $this->isValidImage($primaryImage)) {
            $product->primary_image = $primaryImage;
        } else {
            // Provide a default image if no valid primary image is found
            $product->primary_image = url('/img/default-placeholder.png');
        }

        $images = InventoryImage::where('product_id', $id)->get();
        $brands = Brand::pluck('name', 'id');

        return view('admin.order-inventory.product-detail', compact('product', 'images', 'brands'));
    }


    public function clearCart()
    {
        // Clear the cart from the session
        session()->forget('order_inventory_cart');

        // Redirect back with a success message
        return redirect()->route('order-inventory.index')->with('success', 'Cart cleared successfully!');
    }


    /**
     * @param Request $request
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function addToCart(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $forCustomer = $request->input('for_customer', false);

        $product = Product::findOrFail($productId);

        // Retrieve or initialize the cart session
        $cart = session()->get('order_inventory_cart', []);

        // Create a unique key based on product_id and for_customer flag
        $cartKey = $productId . '-' . ($forCustomer ? 'customer' : 'inventory');

        if (isset($cart[$cartKey])) {
            // Increment quantity if the same product type (for inventory or customer) is already in cart
            $cart[$cartKey]['quantity'] += $quantity;
        } else {
            // Add new item to cart with a unique key
            $cart[$cartKey] = [
                "product_id" => $productId,
                "name" => $product->name,
                "quantity" => $quantity,
                "price" => $product->price,
                "for_customer" => $forCustomer,
                "preorder" => $product->preorder, // New Pre-Order flag
            ];
        }

        // Save the updated cart back to the session
        session()->put('order_inventory_cart', $cart);

        $viewMode = $request->input('view_mode', 'grid');
        $preOrder = $request->input('pre_order', '0');
        $prev_selected_categories_ids = $request->input('prev_selected_categories_ids', '');
        $categories = $request->input('categories', '');
        return redirect(route('order-inventory.index', ['view_mode' => $viewMode, 'pre_order' => $preOrder, 'prev_selected_categories_ids' => $prev_selected_categories_ids, 'categories' => $categories]))->with('success', 'Product added to cart successfully!');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function removeFromCart(Request $request)
    {
        // Retrieve the unique product key from the request
        $productKey = $request->input('product_key');
        $cart = session()->get('order_inventory_cart', []);

        // Check if the product key exists in the cart and remove it
        if (isset($cart[$productKey])) {
            unset($cart[$productKey]);
            session()->put('order_inventory_cart', $cart);
        }

        // Redirect back to the cart page with an appropriate success message
        return redirect()->route('order-inventory.index')->with('success', 'Product removed from cart successfully!');
    }

    /**
     * @param $id
     * @return JsonResponse
     */
    public function cancelOrder($id): JsonResponse
    {
        $order = OrderInventoryOrder::with('orderItems.product')->findOrFail($id);
        $message = 'Order canceled successfully.';

        // Only loop and restore quantities if order items exist
        if ($order->orderItems->isNotEmpty()) {
            foreach ($order->orderItems as $item) {
                if ($item->product) {
                    $item->product->increment('quantity', $item->quantity);
                    $message .= " Quantity of " . $item->quantity . " added back for product: " . $item->product_id . ". \n";
                }
            }
        }

        $order->update(['status' => 'Canceled']);

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * @param Request $request
     * @param $orderId
     * @return JsonResponse
     */
    public function deleteOrderItem(Request $request, $orderId): JsonResponse
    {

        $order = OrderInventoryOrder::findOrFail($orderId);

        $validated = $request->validate([
            'key' => 'required|string'
        ]);

        $orderItem = $order->orderItems()->where('ID', $validated['key'])->first();

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found in the order.'
            ]);
        }

        $product = $orderItem->product;
        $quantityToRestore = $orderItem->quantity;

        if ($product) {
            $product->increment('quantity', $quantityToRestore);
        }

        $orderItem->delete();

        // Check if any order items remain
        $orderCanceledMessage = "";
        $remainingItems = $order->orderItems()->count();
        if ($remainingItems === 0) {
            $orderCanceledMessage = "No remaining items. Order canceled.";
            $order->update(['status' => 'Canceled']);
            $order->delete();
        }

        return response()->json([
            'success' => true,
            'message' => "Item deleted successfully. " . $orderCanceledMessage . " \nQuantity of: (" . $quantityToRestore . ') added back for Product ID: #' . $product->id,
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function checkout(Request $request)
    {
        // Retrieve the cart from the session
        $cart = session()->get('order_inventory_cart', []);

        // Check if the cart is empty
        if (empty($cart)) {
            return redirect()->route('order-inventory.index')->with('error', 'Your cart is empty.');
        }

        // Initialize an array to track quantity issues
        $quantityIssues = [];
        $productQuantities = [];

        // Aggregate quantities for each product ID
        foreach ($cart as $key => $item) {
            [$productId] = explode('-', $key);
            $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $item['quantity'];
        }

        // Validate cart quantities against product stock
        foreach ($cart as $key => &$item) {
            [$productId] = explode('-', $key);
            $product = Product::find($productId);

            if (!$product || $product->quantity <= 0) {
                unset($cart[$key]);
                $quantityIssues[] = "Requested quantity of {$item['name']} not available. Item removed from cart.";
            } else if ($productQuantities[$productId] > $product->quantity) {
                $excessQuantity = $productQuantities[$productId] - $product->quantity;
                if ($excessQuantity > 0) {
                    $adjustedQuantity = max(0, $item['quantity'] - $excessQuantity);
                    if ($adjustedQuantity > 0) {
                        $quantityIssues[] = "Requested combined quantity of {$item['name']} exceeds stock.";
                    } else {
                        $quantityIssues[] = "Requested quantity of {$item['name']} exceeds stock. Item removed from cart.";
                    }
                    $productQuantities[$productId] -= ($item['quantity'] - $adjustedQuantity);
                }
            }
        }

        // If there were quantity issues, redirect back with an error message
        if (!empty($quantityIssues)) {
            session()->put('order_inventory_cart', $cart);
            return redirect()
                ->route('order-inventory.index')
                ->with('error', implode("\n ", $quantityIssues));
        }

        // Check if any item in the cart is "for customer"
        $hasCustomerItem = collect($cart)->contains(fn($item) => !empty($item['for_customer']));

        // Validation rules
        $rules = [
            'store_number' => 'required|string',
            'order_notes' => 'nullable|string',
            'pickup_date_timestamp' => 'nullable|date',
        ];

        if ($hasCustomerItem) {
            $rules = array_merge($rules, [
                'customer_name' => 'required|string|max:255',
                'is_pickup' => 'required|in:0,1',
                'pickup_by' => 'nullable|string|max:255',
            ]);
        }

        $request->validate($rules);

        // Separate Pre-Order items from the cart
        $preOrderItems = [];
        $regularItems = [];

        foreach ($cart as $key => &$item) {
            [$productId] = explode('-', $key);
            $product = Product::find($productId);

            if ($product && $product->preorder == 1) {
                $preOrderItems[$key] = $item;
            } else {
                $regularItems[$key] = $item;
            }
        }

        // Process Pre-Order items as individual orders
        foreach ($preOrderItems as $key => $preOrderItem) {
            [$productId] = explode('-', $key);
            $product = Product::find($productId);

            if ($product) {
                $product->decrement('quantity', $preOrderItem['quantity']);
            }

            $order = OrderInventoryOrder::create([
                'user_id' => auth()->user()->id,
                'account_id' => auth()->user()->account_id,
                'sub_total' => $preOrderItem['price'] * $preOrderItem['quantity'],
                'customer_name' => $request->input('customer_name', null),
                'is_pickup' => $request->input('is_pickup', '0'),
                'pickup_by' => $request->input('is_pickup') === '1' ? $request->input('pickup_by') : null,
                'pickup_date_timestamp' => $request->input('is_pickup') === '1' ? $request->input('pickup_date_timestamp') : null,
                'order_notes' => $request->input('order_notes', null),
                'store_number' => $request->input('store_number'),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s')
            ]);

            // Insert into pivot table
            $order->orderItems()->create([
                'product_id' => $productId,
                'quantity' => $preOrderItem['quantity'],
                'price' => $preOrderItem['price'],
                'for_customer' => !empty($preOrderItem['for_customer']) ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Process remaining cart items as a single order (if any)
        if (!empty($regularItems)) {
            foreach ($regularItems as $key => $regularItem) {
                [$productId] = explode('-', $key);
                $product = Product::find($productId);

                if ($product) {
                    $product->decrement('quantity', $regularItem['quantity']);
                }
            }

            $subTotal = array_reduce($regularItems, function ($total, $item) {
                return $total + ($item['price'] * $item['quantity']);
            }, 0);

            $order = OrderInventoryOrder::create([
                'user_id' => auth()->user()->id,
                'account_id' => auth()->user()->account_id,
                'sub_total' => $subTotal,
                'customer_name' => $request->input('customer_name', null),
                'is_pickup' => $request->input('is_pickup', '0'),
                'pickup_by' => $request->input('is_pickup') === '1' ? $request->input('pickup_by') : null,
                'pickup_date_timestamp' => $request->input('is_pickup') === '1' ? $request->input('pickup_date_timestamp') : null,
                'order_notes' => $request->input('order_notes', null),
                'store_number' => $request->input('store_number'),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s')
            ]);

            // Insert regular items into the pivot table
            foreach ($regularItems as $key => $regularItem) {
                [$productId] = explode('-', $key);
                $order->orderItems()->create([
                    'product_id' => $productId,
                    'quantity' => $regularItem['quantity'],
                    'price' => $regularItem['price'],
                    'for_customer' => !empty($regularItem['for_customer']) ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Clear the cart from the session
        session()->forget('order_inventory_cart');

        // Redirect with a success message
        return redirect()->route('order-inventory.index')->with('success', 'Order placed successfully!');
    }
}
