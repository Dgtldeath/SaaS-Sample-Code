/******
     * Order Inventory ******
     */
	
	Route::get('/order-inventory/fetch-order-inventory', [OrderInventoryController::class, 'fetchOrderInventoryDataFromAPI']);
	Route::get('/order-inventory/fetch-non-ordered-products', [OrderInventoryController::class, 'getProductsNotInOrderItems']);
    	Route::get('/order-inventory/{id}/quantity', [OrderInventoryController::class, 'getProductQuantity']);
    	Route::post('/order-inventory/{id}/update-quantity', [OrderInventoryController::class, 'updateQuantity']);
    	Route::post('/order-inventory/{id}/delete-item', [OrderInventoryController::class, 'deleteOrderItem'])->name('order-inventory.delete-item');
    	Route::get('order-inventory/{id}/update-status', [OrderInventoryController::class, 'updateStatus'])->name('order-inventory.update-status');
    	Route::post('order-inventory/cart/clear', [OrderInventoryController::class, 'clearCart'])->name('order-inventory.cart.clear');
    	Route::post('/order-inventory/{id}/cancel', [OrderInventoryController::class, 'cancelOrder'])->name('order-inventory.cancel-order');
    	Route::post('/order-inventory/{id}/comments', [OrderInventoryController::class, 'saveComments'])->name('order-inventory.save-comments');
    	Route::get('/order-inventory/{id}/print-view', [OrderInventoryController::class, 'printView'])->name('order-inventory.print-view');
    	Route::get('/order-inventory/fetch-images', [OrderInventoryController::class, 'fetchAndStoreImagesFromAspApi'])->name('order-inventory.fetch-images');
    	Route::get('/order-inventory/orders/export-me', [OrderInventoryController::class, 'exportCsvNow'])->name('order-inventory.exportCsvNow');
    	Route::get('/order-inventory/export-me', [OrderInventoryController::class, 'exportCsvNow'])->name('order-inventory.exportCsvNow');
    	Route::get('/order-inventory', [OrderInventoryController::class, 'index'])->name('order-inventory.index');
    	Route::post('/order-inventory/cart/add', [OrderInventoryController::class, 'addToCart'])->name('order-inventory.cart.add');
    	Route::post('/order-inventory/cart/remove', [OrderInventoryController::class, 'removeFromCart'])->name('order-inventory.cart.remove');
    	Route::post('/order-inventory/cart/checkout', [OrderInventoryController::class, 'checkout'])->name('order-inventory.cart.checkout');
    	Route::post('/order-inventory/{id}/add-to-cart', [OrderInventoryController::class, 'addToCart'])->name('order-inventory.add-to-cart');
    	Route::get('/order-inventory/orders', [OrderInventoryController::class, 'viewOrders'])->name('order-inventory.viewOrders');
    	Route::get('order-inventory/{id}/detail', [OrderInventoryController::class, 'showProductDetail'])->name('order-inventory.product-detail');
    /****** END ORDER INVENTORY *******/
