@extends('layouts.admin.app')

@section('content')

    @if(session('success'))
        <div class="alert alert-success no-print">
            {{ session('success') }}
        </div>
    @endif

    <style>
        @media print {
            p, h4 {
                font-size: 1.6em;
            }
        }

        .d-none {
            display: none;
        }

        #update-status-container {
            width: 50%;
            margin: 30px auto;
        }

        .status-label {
            padding: 0.3em 0.6em;
            border-radius: 4px;
            font-weight: bold;
            color: #fff;
        }

        .status-label.requested {
            background-color: #ffc107; /* Blue for New */
        }

        .status-label.approved {
            background-color: #007bff; /* Yellow for In Progress */
        }

        .status-label.completed {
            background-color: #28a745; /* Green for Fulfilled */
        }

        .status-label.canceled {
            background-color: #dc3545; /* Red for Canceled */
        }

        @media print {
            .no-print {
                display: none;
            }

            .d-print-block {
                display: block;
            }
            p {
                font-size: 1em;
            }
        }

        .top-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-buttons .btn {
            margin: 0; /* Reset any margin applied by default */
        }

        .top-buttons .btn-left {
            text-align: left;
        }

        .top-buttons .btn-right {
            text-align: right;
        }
    </style>

    @php
    if($order->user) {
        $fullName = $order->user->first_name . ' ' . $order->user->last_name;
    }
    else {
        $fullName = "Unknown: ".$order->user_id;
    }
    @endphp

    <div class="ibox" id="main-content-container">
        <div class="ibox-content">
            <div class="container mt-5">

                {{-- Order Details --}}
                <div class="text-center mb-4">
                    <h3 style="text-decoration: underline;">Order Details</h3>

                    <p><strong>Order #:</strong> {{ $order->id }}</p>
                    <p>
                        <strong>Store:</strong>
                        {{ intval($order->store_number) > 0 ? Auth::user()->allStores()->pluck('name', 'store_number')[$order->store_number] ?? 'N/A' : 'N/A' }}
                    </p>
                    <p><strong>Ordered By:</strong> {{ $fullName }}</p>
                    <p><strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('m/d/Y') }} </p>
                    <p><strong>Last Updated:</strong> {{ \Carbon\Carbon::parse($order->updated_at)->format('m/d/Y') }} </p>
                    <p>
                        <strong>Order Status:</strong>
                        <span class="status-label {{ strtolower(str_replace(' ', '-', $order->status)) }}">{{ $order->status }}</span>
                    </p>

                    <div id="update-status-container" class="mt-4 mb-4 no-print">
                        <a href="{{ route('order-inventory.viewOrders') }}" class="btn btn-white mb-3">
                            <i class="fa fa-arrow-left"></i> Back to View Orders
                        </a>
                        &nbsp;
                        <button type="button" class="btn btn-primary btn-right" id="print-button">
                            <i class="fa fa-print"></i> Print
                        </button>
                        &nbsp;

                        @if(Auth::user()->hasRole('admin') && $order->status == "Requested")
                            <button type="button" class="btn btn-success mark-complete-order-btn" data-id="{{ $order->id }}">
                                <i class="fa fa-check"></i> Mark as Complete
                            </button>
                            &nbsp;
                            <button type="button" class="btn btn-danger cancel-order-btn" data-id="{{ $order->id }}">
                                <i class="fa fa-times-circle"></i> Cancel Order
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Order Items Table --}}
                <table class="table table-bordered table-responsive table-striped">
                    <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th>Customer</th>
                        <th class="no-print">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($order->orderItems as $item)
                        <tr id="item-row-{{ $item->id }}">
                            <td>
                                @if($item->product->preorder == 1)
                                    <object aria-label="Pre-Order" type="image/svg+xml" data="/images/preorder-icon.svg" width="30" height="20" style="float: left;"></object>
                                @endif

                                @if(!empty($item->product->name))
                                    <a class="no-print" target="_blank" href="{{ route('order-inventory.product-detail', $item->product->id) }}">
                                        {{ $item->product->name }}
                                    </a>
                                    <span class="d-none d-print-block">{{ $item->product->name }}</span>
                                    <strong>{{ $item->for_customer ? "(Customer)" : '' }}</strong>
                                @else
                                    N/A
                                @endif
                            </td>

                            <td>
                                <span class="quantity-text" id="quantity-text-{{ $item->id }}">{{ $item->quantity }}</span>
                                <input type="number" class="quantity-input form-control d-none"
                                       id="quantity-input-{{ $item->id }}" value="{{ $item->quantity }}" min="1">
                            </td>
                            <td>${{ number_format($item->price, 2) }}</td>
                            <td>${{ number_format($item->quantity * $item->price, 2) }}</td>
                            <td>{{ $item->for_customer ? 'Yes' : 'No' }}</td>
                            <td class="no-print">
                                <button type="button" class="btn btn-white update-qty-btn"
                                        data-key="{{ $item->id }}" data-product-id="{{ $item->product_id }}">
                                    Update Qty
                                </button>
                                <button type="button" class="btn btn-white save-qty-btn d-none"
                                        data-key="{{ $item->id }}" data-order-id="{{ $order->id }}">
                                    Save Qty
                                </button>
                                <button type="button" class="btn btn-danger delete-item-btn"
                                        data-key="{{ $item->id }}" data-order-id="{{ $order->id }}">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="text-right mt-4">
                    <h5>Total: ${{ number_format($order->orderItems->sum(fn($item) => $item->quantity * $item->price), 2) }}</h5>
                </div>

                @php
                    $hasCustomerItem = $order->orderItems->contains(fn($item) => !empty($item->for_customer));
                @endphp

                @if($hasCustomerItem)
                    <h5 class="text-center">Order Details</h5>
                    <div class="mt-4">
                        <p><strong>Customer Name:</strong> {{ $order->customer_name ?? 'N/A' }}</p>
                        <p><strong>Is Pick Up?</strong> {{ $order->is_pickup ? 'Yes' : 'No' }}</p>
                        @if($order->is_pickup)
                            <p><strong>Pickup By:</strong> {{ $order->pickup_by ?? 'N/A' }}</p>
                            <p><strong>Pickup Date:</strong> {{ $order->pickup_date_timestamp ? $order->pickup_date_timestamp->format('m/d/Y H:i') : 'N/A' }}</p>
                        @endif
                        <p><strong>Order Notes:</strong> {{ $order->order_notes ?? 'None' }}</p>
                    </div>
                @endif

                <div class="mt-4 {{ ( strlen($order->comments) <= 1 ? 'no-print' : '' ) }}">
                    <h5>Comments</h5>
                    <form action="{{ route('order-inventory.save-comments', $order->id) }}" method="POST">
                        @csrf
                        <div class="form-group">
                        <textarea name="comments" class="form-control" rows="5"
                                  placeholder="Enter comments">{{ $order->comments }}</textarea>
                        </div>
                        <div class="text-right no-print">
                            <button type="submit" class="btn btn-success mt-2">
                                <i class="fa fa-save"></i> Save Comments
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        var orderItemCount = {{ $order->orderItems()->count() }};

        /** Print Toggle */
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print-toggle')) {
            let currentStatus = "{{$order->status}}";

            if (currentStatus === "Canceled") {
                window.addEventListener('load', function () {
                    window.print(); // only print
                });
            } else {
                window.addEventListener('load', function () {
                    $('#print-button').click(); // Trigger the print action automatically & mark complete
                });
            }
        }


        $(function () {
            // Handle "Mark as Complete" button click
            $('.mark-complete-order-btn').on('click', function () {
                let orderId = $(this).data('id')

                if (!orderId) {
                    alert("Invalid Order ID");
                    return;
                }

                // Confirm the action
                if (confirm('Are you sure you want to mark this order as Complete?')) {
                    $.ajax({
                        url: `/admin/order-inventory/${orderId}/update-status?status=Completed`, // Status set to "Completed"
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}' // CSRF token for security
                        },
                        success: function (data) {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1800,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Refresh the page after the timer ends
                                    window.location.reload();
                                });
                            } else {
                                alert('Failed to update status. Please try again.');
                            }
                        },
                        error: function () {
                            alert('An error occurred while updating the status.');
                        }
                    });
                }
            });

            // Handle "Cancel Order" button click
            $('.cancel-order-btn').on('click', function () {
                let orderId = $(this).data('id'); // Extract order ID from row ID

                if (!orderId) {
                    alert("Invalid Order ID");
                    return;
                }

                // Confirm the action
                if (confirm('Are you sure you want to cancel this order?')) {
                    $.ajax({
                        url: `/admin/order-inventory/${orderId}/update-status?status=Canceled`, // Status set to "Canceled"
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}' // CSRF token for security
                        },
                        success: function (data) {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Success',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1800,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Refresh the page after the timer ends
                                    window.location.reload();
                                });
                            } else {
                                alert('Failed to update status. Please try again.');
                            }
                        },
                        error: function () {
                            alert('An error occurred while updating the status.');
                        }
                    });
                }
            });

            $('.quantity-input').hide();
            $('.save-qty-btn').hide();

            // Handle "Update Qty" button click
            $('.update-qty-btn').on('click', function () {
                const key = $(this).data('key');
                const productId = $(this).data('product-id');
                const updateBtn = $(this);

                // Fetch the current quantity from the database
                $.ajax({
                    url: `/admin/order-inventory/${productId}/quantity`,
                    method: 'GET',
                    success: function (response) {
                        if (response.success) {
                            // Show input field and fetch max quantity
                            const quantityInput = $(`#quantity-input-${key}`);
                            const quantityText = $(`#quantity-text-${key}`);
                            const saveBtn = $(`.save-qty-btn[data-key="${key}"]`);
                            const combinedMax = parseInt(quantityInput.val()) + parseInt(response.currentQuantity);

                            quantityInput.show();
                            quantityInput.attr('max', combinedMax);    // combined
                            quantityInput.removeClass('d-none');
                            quantityText.addClass('d-none');
                            updateBtn.addClass('d-none').hide().next('.save-qty-btn').show();
                            saveBtn.removeClass('d-none');

                            // alert("Current quantity is 0. Quantity can only be adjusted when stock is available.");
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function () {
                        alert('Failed to fetch product quantity. Please try again.');
                    }
                });
            });

            // Handle "Save Qty" button click
            $('.save-qty-btn').on('click', function () {
                const key = $(this).data('key');
                const orderId = $(this).data('order-id');
                const quantityInput = $(`#quantity-input-${key}`);
                const newQuantity = parseInt(quantityInput.val(), 10);

                // Send updated quantity to the server
                $.ajax({
                    url: `/admin/order-inventory/${orderId}/update-quantity`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    data: {
                        key: key,
                        quantity: newQuantity,
                    },
                    success: function (response) {
                        $(`#quantity-text-${key}`).text(newQuantity).removeClass('d-none');

                        quantityInput.addClass('d-none').hide();
                        $(`.update-qty-btn[data-key="${key}"]`).removeClass('d-none').show();
                        $(`.save-qty-btn[data-key="${key}"]`).addClass('d-none').hide();
                        alert(response.message);
                        window.location.reload(false);
                    },
                    error: function () {
                        alert('Failed to update quantity. Please try again.');
                    }
                });
            });

            $(document).on('click', '.delete-item-btn', function () {
                var confirmationMessage = 'Are you sure you want to delete this item?';

                if(orderItemCount == 1) {
                    alert("There is only one item remaining in this order. Removing it will cancel and delete the order.");
                    confirmationMessage = 'Are you sure you want to delete the last item and delete this order?';
                }

                const key = $(this).data('key'); // Get the item key from the button
                const orderId = $(this).data('order-id'); // Get the order ID

                if (confirm(confirmationMessage)) {
                    $.ajax({
                        url: `/admin/order-inventory/${orderId}/delete-item`, // Laravel route
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: {key: key},
                        success: function (response) {
                            if (response.success) {
                                alert(response.message);
                                $(`#item-row-${key}`).remove(); // Remove the deleted row from the table

                                if(orderItemCount > 1) {
                                    orderItemCount--;
                                }
                                else {
                                    // no items left, redirect
                                    window.location.href="/admin/order-inventory/orders/";
                                }
                            } else {
                                alert(response.message || 'Failed to delete the item.');
                            }
                        },
                        error: function () {
                            alert('An error occurred while trying to delete the item.');
                        }
                    });
                }
            });


            $('#update-status-btn').on('click', function () {
                const selectedStatus = $('#status-dropdown').val();

                if (!selectedStatus) {
                    alert('Please select a valid status before updating.');
                    return;
                }

                // Confirm status change
                if (confirm(`Are you sure you want to change the order status to "${selectedStatus}"?`)) {
                    $.ajax({
                        url: '{{ route('order-inventory.update-status', $order->id) }}',
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        data: {status: selectedStatus},
                        success: function (response) {
                            if (response.success) {
                                alert(response.message);
                                location.reload();
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error:', error);
                            alert('Failed to update the status. Please try again.');
                        }
                    });
                }
            });

            $('#print-button').on('click', function () {

                // temporary Direct Print because we don't want to change status anymore:: AMG 12/16/24
                window.print();
                return;

                if ($('body .status-label.completed').length === 1) {  // Already completed
                    window.print();
                    return;
                }

                const printStatus = 'Completed'; // Status to mark the order as completed

                // Perform AJAX call to mark order as completed
                $.ajax({
                    url: '{{ route('order-inventory.update-status', $order->id) }}',
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: {status: printStatus},
                    success: function (response) {
                        if (response.success) {
                            // Update the status label dynamically
                            const statusLabel = $('.status-label');
                            statusLabel.text(printStatus); // Update the text
                            statusLabel
                                .removeClass()
                                .addClass(`status-label ${printStatus.toLowerCase().replace(/ /g, '-')}`);

                            $('#update-status-container').hide();
                            // Trigger the print dialog after successful status update
                            window.print();
                        } else {
                            alert('Failed to update the status. Please try again.');
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('Failed to update the status. Please try again.');
                    }
                });
            });
        });
    </script>

@endsection
