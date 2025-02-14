@extends('layouts.admin.app')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="ibox">
        <div class="ibox-title">
            <a href="{{ route('order-inventory.index') }}" class="btn btn-primary btn-sm pull-right">Back to Order
                Inventory</a>
            <br/><br/>
        </div>

        <div class="ibox-content">
            <h3>Product Inventory Detail</h3>
            <h2 class="text-center">
                <div style="display: flex;
                            flex-direction: row;
                            flex-wrap: nowrap;
                            align-content: center;
                            justify-content: center;
                            align-items: center;">
                {{ $product->name }}

                @if($product->preorder == 1)
                    <object aria-label="Pre-Order" type="image/svg+xml" data="/images/preorder-icon.svg" width="40" height="30"></object>
                @endif
                </div>
            </h2>

            @if($product->trashed())
                <div class="alert alert-warning text-center">
                    <strong>Warning:</strong> This product was deleted on {{ $product->deleted_at->format('F jS, Y g:i A') }}.
                </div>
            @endif

            @if(Auth::user()->hasRole('admin'))
                <div class="row">
                    <div class="col-md-12 text-center">
                        <button class="btn btn-white"
                                onClick="document.location='/admin/product/{{$product->id}}/edit'">
                            <i class="fa fa-pencil"></i> Edit Product
                        </button>
                    </div>
                </div>
            @endif

            <!-- Display Product Placeholder Image -->
            <div class="text-center mb-4" style="margin-bottom: 30px;">
                <div class="cart-section mt-4 mb-4" style="margin-bottom: 20px;">

                    <div class="row">
                        <div class="col-md-6 col-md-push-3"
                             style="margin: 12px auto; padding: 8px 10px; border: 1px solid #ddd; border-radius: 18px;">
                            <h4>Product Ordering</h4>

                            @if($product->quantity > 0)
                                <!-- Shared Quantity Input -->
                                <div class="form-group">
                                    <label for="shared_quantity">Quantity to Order</label>
                                    <input type="number" name="quantity" id="shared_quantity" value="1" min="1"
                                           max="{{ $product->quantity }}"
                                           class="form-control"
                                           style="width: 100px; margin: 0 auto;">
                                </div>

                                <!-- Form to add item to cart for inventory -->
                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id]) }}"
                                      method="POST"
                                      id="add_to_inventory_form" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="for_customer" value="0"> <!-- Inventory -->
                                    <input type="hidden" name="quantity" id="inventory_quantity_input">
                                    <!-- Quantity to be set by JavaScript -->
                                    <input type="hidden" name="redirect_to" value="product-detail">
                                    <button type="button" onclick="submitForm('add_to_inventory_form')"
                                            class="btn btn-primary mt-2">
                                        <i class="fa fa-list"></i> Add to Inventory
                                    </button>
                                </form>

                                <!-- Form to add item to cart for customer -->
                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id]) }}"
                                      method="POST"
                                      id="add_to_customer_form" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="for_customer" value="1"> <!-- Customer -->
                                    <input type="hidden" name="quantity" id="customer_quantity_input">
                                    <!-- Quantity to be set by JavaScript -->
                                    <input type="hidden" name="redirect_to" value="product-detail">
                                    <button type="button" onclick="submitForm('add_to_customer_form')"
                                            class="btn btn-secondary mt-2">
                                        <i class="fa fa-users"></i> Add for Customer
                                    </button>
                                </form>
                            @else
                                No inventory available
                            @endif
                        </div>
                    </div>
                </div>

                <img src="{{ $product->primary_image }}"
                     alt="Product Image" class="img-fluid mb-3" style="max-width: 400px; min-height: 300px;"/>
            </div>

            <!-- Display Product Details -->
            <table class="table table-bordered">
                <thead>
                <tr>
                    @if($product->name)
                        <th>Name</th>
                    @endif
                    @if($product->sku)
                        <th>SKU</th>
                    @endif
                    @if($product->categories && $product->categories->isNotEmpty())
                        <th>Category</th>
                    @endif
                    @if(@$product->brand->name)
                        <th>Brand</th>
                    @endif
                    <th>Quantity on Hand</th>
                    @if($product->description)
                        <th>Description</th>
                    @endif
                    @if($product->model_number)
                        <th>Model Number</th>
                    @endif
                    @if($product->condition)
                        <th>Condition</th>
                    @endif
                    @if($product->weekly_price)
                        <th>Weekly Price</th>
                    @endif
                    @if($product->weekly_terms)
                        <th>Weekly Terms</th>
                    @endif
                    @if($product->monthly_price)
                        <th>Monthly Price</th>
                    @endif
                    @if($product->monthly_terms)
                        <th>Monthly Terms</th>
                    @endif
                    @if($product->price)
                        <th>Price</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                <tr>
                    @if($product->name)
                        <td>{{ $product->name }}</td>
                    @endif
                    @if($product->sku)
                        <td>{{ $product->sku }}</td>
                    @endif
                    @if($product->categories && $product->categories->isNotEmpty())
                        <td>
                            @foreach ($product->categories as $category)
                                <span class="simple_tag"
                                      style="display: inline-block; margin-right: 5px;">{{ $category->name }}</span>
                            @endforeach
                        </td>
                    @endif
                    @if(@$product->brand->name)
                        <td>{{ $product->brand->name }}</td>
                    @endif
                    <td> {{ $product->quantity }}</td>
                    @if($product->description)
                        <td>{!! htmlspecialchars_decode($product->description) !!}</td>
                    @endif
                    @if($product->model_number)
                        <td>{{ $product->model_number }}</td>
                    @endif
                    @if($product->condition)
                        <td>{{ $product->condition }}</td>
                    @endif
                    @if($product->weekly_price)
                        <td>{{ $product->weekly_price }}</td>
                    @endif
                    @if($product->weekly_terms)
                        <td>{{ $product->weekly_terms }}</td>
                    @endif
                    @if($product->monthly_price)
                        <td>{{ $product->monthly_price }}</td>
                    @endif
                    @if($product->monthly_terms)
                        <td>{{ $product->monthly_terms }}</td>
                    @endif
                    @if($product->price)
                        <td>${{ number_format($product->price, 2) }}</td>
                    @endif
                </tr>
                </tbody>
            </table>

        </div>
    </div>

    <script>
        function submitForm(formId) {
            // Get the shared quantity value
            const quantity = document.getElementById('shared_quantity').value;

            // Set the quantity in the appropriate hidden input field
            if (formId === 'add_to_inventory_form') {
                document.getElementById('inventory_quantity_input').value = quantity;
            } else if (formId === 'add_to_customer_form') {
                document.getElementById('customer_quantity_input').value = quantity;
            }

            // Submit the form
            document.getElementById(formId).submit();
        }
    </script>
@endsection
