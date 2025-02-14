@extends('layouts.admin.app')

@section('content')

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <style>
        #button-container button, #button-container a, #button-container input {
            margin-bottom: 5px;
            margin-top: 4px;
        }

        #button-container {
            display: flex;
            flex-wrap: wrap;
            align-content: center;
            justify-content: center;
            align-items: center;
            flex-direction: row;
            column-gap: 1px;
        }

        .d-print-block {
            display: none;
        }

        @media print {
            .d-print-block {
                display: block;
            }
        }

        #action-container button, #action-container a {
            margin-bottom: 10px;
        }
    </style>

    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <div class="">

        {{-- Search Controls Section --}}
        <div class="ibox no-print" id="search-controls-container">
            <div class="ibox-content">
                <form action="{{ route('order-inventory.viewOrders') }}" method="GET">
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <div class="col-md-3">
                                <label for="status">Status:</label>
                                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                                    <option value="Requested" {{ request('status') == 'Requested' ? 'selected' : '' }}>
                                        Requested
                                    </option>
                                    <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>
                                        Completed
                                    </option>
                                    <option value="Canceled" {{ request('status') == 'Canceled' ? 'selected' : '' }}>
                                        Canceled
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status">Store:</label>
                                <select name="store" id="store" class="form-control" onchange="this.form.submit()">
                                    <option value="">Filter by Store</option>
                                    @php
                                        // Step 1: Collect all store_numbers from the orders
                                        $storeNumbersInOrders = $orders->pluck('store_number')->unique()->toArray();

                                        // Step 2: Get stores from myStoreByStoreNumber() and filter based on storeNumbersInOrders
                                        $allStores = Auth::user()->myStoreByStoreNumber();
                                        $filteredStores = array_filter($allStores, function ($storeName, $storeNumber) use ($storeNumbersInOrders) {
                                            return in_array($storeNumber, $storeNumbersInOrders);
                                        }, ARRAY_FILTER_USE_BOTH);
                                    @endphp

                                            <!-- Step 3: Populate the dropdown -->
                                    @foreach($filteredStores as $storeNumber => $storeName)
                                        <option value="{{ $storeNumber }}" {{ request('store') == $storeNumber ? 'selected' : '' }}>
                                            {{ $storeName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="start_date">Start Date:</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                       value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date">End Date:</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                       value="{{ request('end_date') }}">
                            </div>
                        </div>


                        <div class="col-md-5" id="button-container">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="pre_order" name="pre_order" value="1"
                                       {{ request('pre_order') == 1 ? 'checked' : '' }} onchange="this.form.submit()" />
                                <label class="form-check-label" for="pre_order">PreOrders</label>
                            </div>

                            <input type="button" class="btn btn-secondary" value="Reset"
                                   onClick="document.location='{{ route('order-inventory.viewOrders') }}'"/>
                            &nbsp;
                            <button type="submit" class="btn btn-primary mt-4">
                                <i class="fa fa-filter"></i> Filter
                            </button>
                            &nbsp;
                            <a href="{{ route('order-inventory.exportCsvNow', request()->query()) }}"
                               class="btn btn-success text-white">
                                <i class="fa fa-file"></i> Export
                            </a>
                            &nbsp;
                            <a href="#" onclick="window.print();" class="btn btn-white">
                                <i class="fa fa-print"></i> Print
                            </a>
                            &nbsp;
                            <a href="{{ route('order-inventory.index') }}" class="btn btn-primary">
                                <i class="fa fa-arrow-circle-left"></i> Back
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Main Content Section --}}
        <div class="ibox" id="main-content-container">
            <div class="ibox-title">
                <h5>Orders</h5>
            </div>

            <div class="ibox-content">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Ordered By</th>
                        <th>Store</th>
                        <th>Customer</th>
                        <th>Qty</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        @foreach($order->orderItems as $item)
                            @if( request('pre_order') == 1 && $item->product->preorder == 0 )
                                @continue
                            @endif

                            <tr id="order-row-{{ $order->id }}">
                                <td>{{ $order->id }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->created_at)->format('m/d/Y') }}</td>
                                <td>{{ optional($order->user)->first_name . ' ' . ( optional($order->user)->last_name ?? 'N/A' ) }}</td>
                                <td>{{ intval($order->store_number) > 0 ? Auth::user()->allStores()->pluck('name', 'store_number')[$order->store_number] ?? 'N/A' : 'N/A' }}</td>
                                <td>{{ ( strlen($order->customer_name) < 2 ? 'N/A' : $order->customer_name) }}</td>

                                <!-- Quantity Column -->
                                <td>{{ $item['quantity'] }}</td>

                                <!-- Item Name Column -->
                                <td>
                                    @if($item->product->preorder == 1)
                                        <object aria-label="Pre-Order" type="image/svg+xml"
                                                data="/images/preorder-icon.svg" width="30" height="20"
                                                style="float: left;"></object>
                                    @endif

                                    @if(!empty($item->product->name))
                                        <a class="no-print" target="_blank"
                                           href="{{ route('order-inventory.product-detail', $item->product->id) }}">
                                            {{ $item->product->name }}
                                        </a>
                                        <span class="d-none d-print-block">{{ $item->product->name }}</span>
                                        <strong>{{ $item->for_customer ? "(Customer)" : '' }}</strong>
                                    @else
                                        N/A
                                    @endif
                                </td>

                                <!-- Status Column -->
                                <td>
                                    <span id="order-status-{{ $order->id }}">{{ $order->status }}</span>
                                </td>

                                <!-- Actions Column -->
                                <td class="no-print">
                                    <div class="mb-2 nowrap" id="action-container">
                                        @if( (Auth::user()->hasRole('admin') || Auth::user()->position_id == 56) && $order->status == "Requested")
                                            <button title="Mark as Complete and Print" type="button"
                                                    class="btn btn-success mark-complete-order-btn"
                                                    data-order="{{ $order->id }}">
                                                <i class="fa fa-check"></i> + <i class="fa fa-print"></i>
                                            </button>
                                        @endif
                                        &nbsp;

                                        @if(Auth::user()->hasRole('admin') && $order->status !== "Canceled")
                                            <button type="button" title="Cancel Order"
                                                    class="btn btn-danger cancel-order-btn">
                                                <i class="fa fa-times-circle"></i>
                                            </button>
                                        @endif

                                        @if( !Auth::user()->hasRole('admin') && $order->status == "Requested")
                                            <button type="button" title="Cancel Order"
                                                    class="btn btn-danger cancel-order-btn">
                                                <i class="fa fa-times-circle"></i>
                                            </button>
                                        @endif
                                        &nbsp;
                                        <a title="View Order"
                                           href="{{ route('order-inventory.print-view', $order->id) }}"
                                           class="btn btn-primary">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        &nbsp;
                                        <a title="Print" data-order="{{ $order->id }}"
                                           href="{{ route('order-inventory.print-view', ['id' => $order->id, 'print-toggle' => 'true']) }}"
                                           class="btn btn-white print-order-btn">
                                            <i class="fa fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="9">No orders to show</td>
                        </tr>
                    @endforelse


                    </tbody>
                </table>

                <center>{!! $orders->appends(Request::except('page'))->links() !!}</center>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            // Handle "Mark as Complete" button click
            $('.mark-complete-order-btn').on('click', function () {
                const orderId = $(this).closest('tr').attr('id').replace('order-row-', ''); // Extract order ID from row ID

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
                                const row = $(`#order-row-${orderId}`);
                                row.css('transition-duration', '300ms').css('background-color', 'lightgreen');

                                $('#order-status-' + orderId).text('Completed');

                                setTimeout(() => row.css('background-color', ''), 3000);

                                Swal.fire({
                                    title: 'Success',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1800,
                                    showConfirmButton: false
                                });

                                const printButton = $(`.print-order-btn[data-order="${orderId}"]`);
                                if (printButton.length) {
                                    setTimeout(() => printButton[0].click(), 1900); // 2-second delay before printing
                                }
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
                const orderId = $(this).closest('tr').attr('id').replace('order-row-', ''); // Extract order ID from row ID

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
                                const row = $(`#order-row-${orderId}`);
                                row.css('transition-duration', '300ms').css('background-color', 'red');

                                $('#order-status-' + orderId).text('Canceled');

                                setTimeout(() => row.css('background-color', ''), 3000);

                                Swal.fire({
                                    title: 'Success',
                                    text: data.message,
                                    icon: 'success',
                                    showConfirmButton: true
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
        });
    </script>

@endsection
