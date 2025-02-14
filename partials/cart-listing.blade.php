@if(session('order_inventory_cart') && count(session('order_inventory_cart')) > 0)
    @php
        $showPreOrderColumn = false;
        foreach(session('order_inventory_cart', []) as $key => $details) {
            if( isset($details['preorder']) && $details['preorder'] == 1) {
                $showPreOrderColumn = true;
                break;
            }
        }

        $allMyStores = Auth::user()->myStoreByStoreNumber();
        asort($allMyStores);
    @endphp

    <table class="table table-bordered">
        <thead>
        <tr>
            <th>Name</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Customer?</th>
            <th>Remove</th>
        </tr>
        </thead>
        <tbody id="cart-items">
        @foreach(session('order_inventory_cart', []) as $key => $details)
            <tr data-id="{{ $key }}">
                <td>
                    @if($showPreOrderColumn && $details['preorder'] == 1)
                        <object aria-label="Pre-Order" type="image/svg+xml" data="/images/preorder-icon.svg" width="30"
                                height="20" style="float: left;"></object>
                    @endif
                    {{ $details['name'] }}
                </td>
                <td>{{ $details['quantity'] }}</td>
                <td class="price">${{ number_format($details['price'], 2) }}</td>
                <td>
                    <input type="checkbox"
                           disabled {{ !empty($details['for_customer']) ? 'checked' : '' }}> {{ !empty($details['for_customer']) ? 'Yes' : 'No' }}
                </td>
                <td>
                    {{-- Independent Remove Item Form --}}
                    <form action="{{ route('order-inventory.cart.remove') }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="product_key" value="{{ $key }}">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Checkout Form --}}
    <form action="{{ route('order-inventory.cart.checkout') }}" method="POST" id="cart-form">
        @csrf

        <h5 class="text-center">Order Details</h5>

        {{-- Select Store --}}
        <div class="form-group">
            <label for="store_selector">Select Store</label>
            <select name="store_number" id="store_selector" class="form-control">
                @if( count($allMyStores) == 1 )
                    @foreach($allMyStores as $storeNumber => $storeName)
                    <option value="{{ $storeNumber }}" {{ old('store_number', session('store_number')) == $storeNumber ? 'selected' : '' }}>
                        {{ $storeName }}
                    </option>
                    @endforeach
                @else
                    <option value="" disabled selected>Select a Store</option>
                    @foreach($allMyStores as $storeNumber => $storeName)
                        <option value="{{ $storeNumber }}" {{ old('store_number', session('store_number')) == $storeNumber ? 'selected' : '' }}>
                            {{ $storeName }}
                        </option>
                    @endforeach
                @endif
            </select>
        </div>

        @php
            $hasCustomerItem = collect(session('order_inventory_cart'))->contains(fn($item) => !empty($item['for_customer']));
        @endphp

        @if($hasCustomerItem)
            {{-- Customer Name --}}
            <div class="form-group">
                <label for="customer_name">Customer Name</label>
                <input type="text" name="customer_name" id="customer_name" class="form-control"
                       value="{{ old('customer_name', session('customer_name')) }}">
            </div>

            {{-- Is Pickup --}}
            <div class="form-group">
                <label for="is_pickup">Is Pick Up?</label>
                <select name="is_pickup" id="is_pickup" class="form-control">
                    <option value="0" {{ old('is_pickup', session('is_pickup')) == '0' ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('is_pickup', session('is_pickup')) == '1' ? 'selected' : '' }}>Yes</option>
                </select>
            </div>

            {{-- Pickup By --}}
            <div class="form-group">
                <label for="pickup_by">Pickup By (Name)</label>
                <input type="text" name="pickup_by" id="pickup_by" class="form-control"
                       value="{{ old('pickup_by', session('pickup_by')) }}">
            </div>

            {{-- Pickup Date --}}
            <div class="form-group">
                <label for="pickup_date_timestamp">Pickup Date</label>
                <input type="datetime-local" name="pickup_date_timestamp" id="pickup_date_timestamp"
                       class="form-control"
                       value="{{ old('pickup_date_timestamp', session('pickup_date_timestamp')) }}">
            </div>

            {{-- Order Notes --}}
            <div class="form-group">
                <label for="order_notes">Order Notes</label>
                <textarea name="order_notes" id="order_notes"
                          class="form-control">{{ old('order_notes', session('order_notes')) }}</textarea>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mt-2">
            @php
                $totalPrice = number_format(array_sum(array_map(function ($item) {
                    return $item['price'] * $item['quantity'];
                }, session('order_inventory_cart') ?? [])), 2);
            @endphp

            <h5>Total Price: <span id="total-price">${{ $totalPrice }}</span></h5>
            <button id="place-order-btn" class="btn btn-primary btn-sm">Place Order</button>
        </div>
    </form>

    {{-- Clear Cart Button --}}
    <form action="{{ route('order-inventory.cart.clear') }}" method="POST" style="margin-top: 15px;">
        @csrf
        <button type="submit" class="btn btn-danger btn-sm" id="clear-cart-btn">Clear Cart</button>
    </form>
@else
    <p>Your cart is empty.</p>
@endif

<script>
    $(document).ready(function () {

        $('#clear-cart-btn').on('click', function () {
            // Clear all localStorage keys related to the cart form
            localStorage.removeItem('store_number');
            localStorage.removeItem('customer_name');
            localStorage.removeItem('is_pickup');
            localStorage.removeItem('pickup_by');
            localStorage.removeItem('pickup_date_timestamp');
            localStorage.removeItem('order_notes');
        });

        $('#place-order-btn').on('click', function () {
            // Clear all localStorage keys related to the cart form
            localStorage.removeItem('store_number');
            localStorage.removeItem('customer_name');
            localStorage.removeItem('is_pickup');
            localStorage.removeItem('pickup_by');
            localStorage.removeItem('pickup_date_timestamp');
            localStorage.removeItem('order_notes');
        });

        const now = new Date();

        // Format the date as YYYY-MM-DDTHH:MM
        const formattedDate = now.toISOString().slice(0, 16);

        // Set the minimum value dynamically
        $('#pickup_date_timestamp').attr('min', formattedDate);

        $('form#cart-form').on('submit', function (e) {
            const selectedStore = $('#store_selector').val();

            if (!selectedStore) {
                alert('Please select a store before submitting the form.');
                e.preventDefault();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        // Persist form values to localStorage
        const inputs = document.querySelectorAll('#cart-form input, #cart-form select, #cart-form textarea');

        inputs.forEach(input => {
            // Load saved value from localStorage
            const savedValue = localStorage.getItem(input.name);
            if (savedValue) {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = savedValue === 'true';
                } else {
                    input.value = savedValue;
                }
            }

            // Save value on change
            input.addEventListener('change', function () {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    localStorage.setItem(input.name, input.checked);
                } else {
                    localStorage.setItem(input.name, input.value);
                }
            });
        });
    });
</script>
