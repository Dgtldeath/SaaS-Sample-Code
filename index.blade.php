@extends('layouts.admin.app')

@section('page-styles')
    <style>
        .nowrap {
            white-space: nowrap;
        }
        
        .dropdown.keep-open .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
            min-width: 300px;
        }

        #jstree {
            padding: 10px;
        }

        .mb-1 {
            margin-bottom: 1em !important;
        }

        @media (max-width: 768px) {
            .col-md-7 {
                order: 2;
            }
            .col-md-5 {
                order: 1;
            }
        }

        @media (max-width: 1288px) {
            #search_button {
                margin-bottom: 1em !important;
            }
        }

        @media (max-width: 991px) {
            #search_button {
                margin-bottom: 0 !important;
            }
        }

    </style>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @elseif(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="">
        <div class="ibox" id="search-controls-container">
            <div class="ibox-content">
                <form action="{{ route('order-inventory.index') }}" method="GET">
                    <div class="row mb-3">
                        <div class="col-md-4 mb-1">
                            <input type="text" name="search" class="form-control" placeholder="Search..."
                                   value="{{ request('search') }}">
                        </div>

                        <div class="col-md-2 mb-1">
                            <!-- Hidden input for current categories -->
                            <input type="hidden" name="categories" value="" id="selected_categories">

                            <!-- Hidden input for previously selected categories -->
                            <input type="hidden" name="prev_selected_categories_ids"
                                   value="{{ implode(',', (request()->input('categories', []) ?: [])) }}"
                                   id="prev_selected_categories_ids">

                            <!-- Dropdown -->
                            <div class="dropdown keep-open">
                                <button id="dLabel" role="button" href="#"
                                        data-toggle="dropdown" data-target="#"
                                        class="btn btn-primary">
                                    Select Category <span class="caret"></span>
                                </button>

                                <ul class="dropdown-menu" role="menu"
                                    aria-labelledby="dLabel">
                                    <div id="jstree"></div>
                                </ul>
                            </div>
                            
                            <!-- Updated unique input for category IDs -->
                            <input type="hidden" name="category_ids" id="category_ids"
                                   value="{{ request('category_ids', '') }}">

                            <!-- Previously selected categories -->
                            <input type="hidden" id="prev_selected_category_ids"
                                   value="{{ is_array(request('category_ids')) ? implode(',', request('category_ids')) : request('category_ids') }}">
                        </div>
                        <div class="col-md-1">
                                <label for="pre_order" class="nowrap">
                                    <input type="checkbox" name="pre_order" id="pre_order" value="1"
                                        @if (request('pre_order', false)) checked @endif>
                                    Pre-Order
                                </label>
                            </div>
                        <div class="col-md-2 mb-1">
                            <input type="submit" id="search_button" class="btn btn-primary" value="Search"/>
                            <input type="button" class="btn btn-secondary" value="Reset"
                                   onClick="document.location='{{ route('order-inventory.index') }}'"/>
                        </div>

                        <div class="col-md-3 mb-1">

                                <a href="{{ route('order-inventory.viewOrders') }}" class="btn btn-primary mb-3">
                                    <i class="fa fa-list"></i> View Orders
                                </a>

                            <br/>
                            <br/>
                            <!-- Toggle Buttons for View Mode -->
                            @if($viewMode == 'table')
                                <a href="{{ route('order-inventory.index', ['view_mode' => 'grid']) }}"
                                   class="btn btn-primary {{ $viewMode == 'grid' ? 'active' : '' }}">
                                    <i class="fa fa-th-large"></i> Toggle Grid View
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7 content-left">

                <!-- Table View -->
                @if(($viewMode ?? 'table') == 'table')
                    <div class="ibox">
                        <div class="ibox-title">
                            <h5>Order Inventory - {{ @ucwords($viewMode) }} View</h5>
                        </div>
                        <div class="ibox-content">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Name</th>
                                    <th>Model Number</th>
                                    <th>Qty On Hand</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($products as $product)
                                    <tr>
                                        <td>
                                            @foreach($product->categories as $category)
                                                <span class="simple_tag">{{ $category->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            <a href="{{ route('order-inventory.product-detail', $product->id) }}">{{ $product->name }}</a>
                                        </td>
                                        <td>{{ $product->model_number }}</td>
                                        <td>
                                            <p style="font-weight: bold;">
                                                @if($product->quantity <= 5)
                                                    <span class="text-danger bolder">{{ $product->quantity }}</span>
                                                @else
                                                    {{ $product->quantity }}
                                                @endif
                                            </p>
                                        </td>
                                        <td nowrap="nowrap">
                                            <div class="btn-group" style="{{ ($product->quantity <= 0 ? 'display: none;' : '') }}">
                                                <label for="quantity_{{ $product->id }}"
                                                       style="display: inline-block; margin-right: 5px;">Qty:</label>
                                                <input type="number" id="quantity_{{ $product->id }}"
                                                       name="quantity" value="1" min="1" max="{{ $product->quantity }}"
                                                       class="form-control"
                                                       style="width: 60px; display: inline-block; margin-right: 10px;">
                                                <br/>
                                                <!-- Form to add item to cart for inventory -->
                                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id]) }}"
                                                      method="POST"
                                                      id="add_to_inventory_form_{{ $product->id }}"
                                                      style="display:inline;">
                                                    @csrf
                                                    <input type="hidden" name="product_id"
                                                           value="{{ $product->id }}">
                                                    <input type="hidden" name="for_customer" value="0">
                                                    <input type="hidden" name="view_mode" value="table">
                                                    <!-- Persist view_mode -->
                                                    <!-- Inventory -->
                                                    <input type="hidden" name="quantity">
                                                    <!-- Quantity will be set dynamically -->
                                                    <button type="button"
                                                            onclick="submitForm('add_to_inventory_form_{{ $product->id }}', 'quantity_{{ $product->id }}')"
                                                            class="btn btn-primary"
                                                            style="margin-bottom: 5px; margin-top: 5px; width: 160px;">
                                                        <i class="fa fa-list"></i> Add to Inventory
                                                    </button>
                                                </form>

                                                <br/>

                                                <!-- Form to add item to cart for customer -->
                                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id]) }}"
                                                      method="POST"
                                                      id="add_to_customer_form_{{ $product->id }}"
                                                      style="display:inline;">
                                                    @csrf
                                                    <input type="hidden" name="product_id"
                                                           value="{{ $product->id }}">
                                                    <input type="hidden" name="for_customer" value="1">
                                                    <input type="hidden" name="view_mode" value="table">
                                                    <!-- Persist view_mode -->
                                                    <!-- Customer -->
                                                    <input type="hidden" name="quantity">
                                                    <!-- Quantity will be set dynamically -->
                                                    <button type="button"
                                                            onclick="submitForm('add_to_customer_form_{{ $product->id }}', 'quantity_{{ $product->id }}')"
                                                            class="btn btn-secondary"
                                                            style="margin-bottom: 5px; width: 160px;">
                                                        <i class="fa fa-users"></i> Add for Customer
                                                    </button>
                                                </form>
                                            </div>
                                            @if($product->quantity <= 0)
                                                No available stock
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <center>{!! $products->appends(Request::except('page'))->render() !!}</center>
                        </div>
                    </div>
                @endif

                <!-- Grid View -->
                @if( @$viewMode == 'grid')
                    <div class="ibox">
                        <div class="ibox-title">
                            <h5>Order Inventory - {{ @ucwords($viewMode) }} View</h5>
                        </div>
                        <div class="ibox-content">
                            <div class="container" style="max-width: 100%;">
                                <div class="row">
                                    @foreach($products->chunk(4) as $productRow)
                                        <!-- Chunk products into rows of 3 -->
                                        <div class="row mb-3" style="margin-bottom: 30px;">
                                            @foreach($productRow as $product)
                                                <div class="col-xs-6 col-sm-4 col-md-4 col-lg-3 mb-4">
                                                    <div class="card">
                                                        <div class="product-image-container"
                                                             style="height: 180px; margin-bottom: 20px; overflow: hidden;">
                                                            <a href="{{ route('order-inventory.product-detail', $product->id) }}">
                                                                <img src="{{ $product->primary_image ?? 'https://i5.walmartimages.com/seo/HONBAY-L-Shaped-Sofa-Sectional-Couch-with-Movable-Ottoman-for-Living-Room-Furniture-Set-Gray_1f6ab555-11f7-4420-bd59-c66245930aba.14a2bc64de793ed8dd32161b9ab1de7d.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF' }}"
                                                                     class="card-img-top"
                                                                     alt="Product Image"
                                                                     style="max-width: 100%; max-height: 300px;">
                                                            </a>
                                                        </div>
                                                        <div class="card-body" style="display: flex;
                                                                flex-direction: column;
                                                                flex-wrap: nowrap;
                                                                align-content: center;
                                                                justify-content: end;
                                                                align-items: flex-start;">

                                                            <div style="min-height: 90px; overflow: hidden; margin-bottom: 10px;">
                                                                <a href="{{ route('order-inventory.product-detail', $product->id) }}">
                                                                    <h5 class="card-title">{{ $product->name }}</h5>
                                                                    <p style="font-size: 9px; line-height: 9px;"><strong>Model Number:</strong></p>
                                                                    <p style="line-height: 13px;">{{ $product->model_number }}</p>
                                                                </a>
                                                            </div>

                                                            <p>
                                                                <strong>Qty On Hand:</strong>
                                                                @if($product->quantity <= 5)
                                                                    <span class="text-danger bolder">{{ $product->quantity }}</span>
                                                                @else
                                                                    {{ $product->quantity }}
                                                                @endif
                                                            </p>
                                                            <div class="btn-group" style="{{ ($product->quantity <= 0 ? 'display: none;' : '') }}">

                                                                <label for="quantity_{{ $product->id }}"
                                                                       style="display: inline-block; margin-right: 5px;">Qty:</label>
                                                                <input type="number" id="quantity_{{ $product->id }}"
                                                                       name="quantity" value="1" min="1"
                                                                       max="{{ $product->quantity }}"
                                                                       class="form-control"
                                                                       style="width: 60px; display: inline-block; margin-right: 10px;">
                                                                <br/>
                                                                <!-- Form to add item to cart for inventory -->
                                                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id, 'pre_order' => $product->preorder, 'prev_selected_categories_ids' => request("prev_selected_categories_ids"), 'categories' => request('categories')]) }}"
                                                                      method="POST"
                                                                      id="add_to_inventory_form_{{ $product->id }}"
                                                                      style="display:inline;">
                                                                    @csrf
                                                                    <input type="hidden" name="product_id"
                                                                           value="{{ $product->id }}">
                                                                    <input type="hidden" name="for_customer" value="0">
                                                                    <!-- Inventory -->
                                                                    <input type="hidden" name="quantity">
                                                                    <!-- Quantity will be set dynamically -->
                                                                    <button type="button"
                                                                            onclick="submitForm('add_to_inventory_form_{{ $product->id }}', 'quantity_{{ $product->id }}')"
                                                                            class="btn btn-primary"
                                                                            style="margin-bottom: 5px; margin-top: 5px; width: 160px;">
                                                                        <i class="fa fa-list"></i> Add to Inventory
                                                                    </button>
                                                                </form>

                                                                <br/>

                                                                <!-- Form to add item to cart for customer -->
                                                                <form action="{{ route('order-inventory.add-to-cart', ['id' => $product->id, 'pre_order' => $product->preorder, 'prev_selected_categories_ids' => request("prev_selected_categories_ids"), 'categories' => request('categories')]) }}"
                                                                      method="POST"
                                                                      id="add_to_customer_form_{{ $product->id }}"
                                                                      style="display:inline;">
                                                                    @csrf
                                                                    <input type="hidden" name="product_id"
                                                                           value="{{ $product->id }}">
                                                                    <input type="hidden" name="for_customer" value="1">
                                                                    <!-- Customer -->
                                                                    <input type="hidden" name="quantity">
                                                                    <!-- Quantity will be set dynamically -->
                                                                    <button type="button"
                                                                            onclick="submitForm('add_to_customer_form_{{ $product->id }}', 'quantity_{{ $product->id }}')"
                                                                            class="btn btn-secondary"
                                                                            style="margin-bottom: 5px; width: 160px;">
                                                                        <i class="fa fa-users"></i> Add for Customer
                                                                    </button>
                                                                </form>
                                                            </div>
                                                            @if($product->quantity <= 0)
                                                                No available stock
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                                <center>{!! $products->appends(Request::except('page'))->links() !!}</center>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Cart Sidebar -->
            <div class="col-md-5 content-right mb-1">
                <div class="ibox-content">
                    <h4>Your Cart</h4>
                    <div id="cart-listing">
                        @include('admin.order-inventory.partials.cart-listing')
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('page-scripts')
    <script src="/js/plugins/summernote/summernote.min.js"></script>
    <script src="/js/plugins/validate/jquery.validate.min.js"></script>
    <script src="/js/plugins/validate/additional-methods.min.js"></script>

    <script>

        document.addEventListener("DOMContentLoaded", function () {
            function reorderColumns() {
                const left = document.querySelector('.content-left');
                const right = document.querySelector('.content-right');

                if (!left || !right) {
                    console.error('Elements with classes .content-left or .content-right not found.');
                    return;
                }

                const parent = left.parentNode;

                if (!parent) {
                    console.error('Parent element not found.');
                    return;
                }

                // Check if screen width is at smaller breakpoint or below
                if (window.innerWidth <= 991) {
                    if (right.nextElementSibling !== left) {
                        console.log('Reordering: Moving .content-right above .content-left for mobile.');
                        parent.insertBefore(right, left); // Move "Right" above "Left"
                    }
                } else {
                    // Restore the original order for larger screens
                    if (left.nextElementSibling !== right) {
                        console.log('Reordering: Moving .content-left above .content-right for desktop.');
                        parent.insertBefore(left, right); // Ensure "Left" is above "Right"
                    }
                }
            }

            // Initial call on page load
            reorderColumns();

            // Attach the reorder function to the window resize event
            window.addEventListener('resize', reorderColumns);
        });

        function submitForm(formId, quantityId) {
            // Get the quantity value for the specific product
            const quantity = document.getElementById(quantityId).value;

            // Set the quantity value in the form's hidden input field
            const form = document.getElementById(formId);
            const quantityInput = form.querySelector('input[name="quantity"]');

            if (quantityInput) {
                quantityInput.value = quantity; // Set the value
            } else {
                console.error('Quantity input not found in form:', formId);
            }

            // Submit the form
            form.submit();
        }

        var tree;
        var tinyMce;
        var featured_products;
        var related_products;
        var category_status_changed = false;

        function disable(node_id) {
            var node = $("#jstree").jstree().get_node(node_id);
            $("#jstree").jstree().disable_node(node);
            node.children.forEach(function (child_id) {
                disable(child_id);
            });
        }

        function enable(node_id) {
            var node = $("#jstree").jstree().get_node(node_id);
            $("#jstree").jstree().enable_node(node);
            node.children.forEach(function (child_id) {
                enable(child_id);
            });
        }

        function enabled_all() {
            $('#jstree >ul > li').each(function () {
                enable(this.id);
            });
        }

        function disabled_all() {
            $('#jstree >ul > li').each(function () {
                disable(this.id);
            })
        }

        $(function () {
            $('.dropdown.keep-open').on({
                "shown.bs.dropdown": function () {
                    this.closable = false;
                },
                "click": function () {
                    this.closable = true;
                },
                "hide.bs.dropdown": function () {
                    return this.closable;
                }
            });
            $('.dropdown-menu').on("click", function (e) {
                e.stopPropagation();
            });

            var tree;
            var selected_nodes;
            var auto_select = false;

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Making of Js Tree
            tree = $('#jstree').jstree({
                'core': {
                    'expand_selected_onload': false,
                    'data': {
                        'url': '{{route('all.category.get')}}',
                    }
                },
                'plugins': ["types", "checkbox", "realcheckboxes"],
                checkbox: {
                    three_state: false,
                    cascade: 'undetermined'
                },
                'types': {
                    'default': {
                        'icon': 'fa fa-folder'
                    }
                },
                sort: function (a, b) {
                }
            }).bind('changed.jstree', function (e, data) {
                var input = $('#categories');
                var curr_selected = data.selected;
                input.val(data.selected.join(','));

                if (auto_select)     //Means initially loaded
                    getAttributes(ids_deleted, ids_added);
            }).bind('deselect_node.jstree', function (e, data) {
                if (data.node.children_d.length > 0)
                    deselect_all_children(data.node.children_d);
            }).bind('ready.jstree', function (e, Data) {
                $('#jstree').jstree("close_all");
                selected_nodes = $('#prev_selected_categories_ids').val().split(',');
                refresh_selected_nodes();
            });

            function deselect_all_children(children) {
                children.forEach(function (child_id) {
                    $('#jstree').jstree("deselect_node", child_id);
                });
            }

            function getAttributes(removed, added) {
                if (added.length > 0) {
                    $.get("/admin/get_category_attributes_view", {ids: added})
                        .done(function (html) {
                            $('#categories_attributes').append(html);
                        });
                }
                removed.forEach(function (id) {
                    $("#category_attr_" + id).remove();
                });
            }

            function refresh_selected_nodes() {
                selected_nodes.forEach(function (id) {
                    $('#jstree').jstree("select_node", id);
                });
                auto_select = true;
            }
        });
    </script>
@endsection
