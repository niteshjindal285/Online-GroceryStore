/**
 * Sales Lines Management - Dynamic Line Items for Orders and Quotes
 */

// Add a new line item row
function addLineItem() {
    const container = document.getElementById('lineItems');
    const firstRow = container.querySelector('.line-item');
    
    if (!firstRow) return;
    
    const newRow = firstRow.cloneNode(true);
    
    // Clear all input values
    newRow.querySelectorAll('input, select').forEach(field => {
        if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
        } else if (field.type !== 'button') {
            if (field.classList.contains('quantity-input')) {
                field.value = '1';
            } else if (field.classList.contains('discount-input')) {
                field.value = '0';
            } else if (field.classList.contains('description-input')) {
                field.value = '';
            } else {
                field.value = '';
            }
        }
    });
    
    container.appendChild(newRow);
    calculateTotals();
}

// Remove a line item row
function removeLineItem(button) {
    const container = document.getElementById('lineItems');
    const items = container.querySelectorAll('.line-item');
    
    if (items.length > 1) {
        button.closest('.line-item').remove();
        calculateTotals();
    } else {
        alert('At least one item is required');
    }
}

// Update unit price when item is selected
async function updateItemPrice(selectElement) {
    const row = selectElement.closest('.line-item');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    const itemId = selectedOption.value;
    
    const priceInput = row.querySelector('.price-input');
    const discountInput = row.querySelector('.discount-input');
    
    if (priceInput) {
        priceInput.value = parseFloat(price).toFixed(2);
        
        // Fetch customer discount if customer is selected
        const customerSelect = document.getElementById('customer_id');
        if (customerSelect && customerSelect.value && itemId) {
            try {
                const response = await fetch(`ajax_get_customer_discount.php?customer_id=${customerSelect.value}&item_id=${itemId}`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.discount_percent !== undefined && data.discount_percent !== null && discountInput) {
                        // Only auto-apply if current discount is 0 to avoid overwriting user edits, 
                        // or just overwrite it because they just selected the item
                        discountInput.value = parseFloat(data.discount_percent).toFixed(2);
                    }
                }
            } catch (error) {
                console.error("Error fetching discount:", error);
            }
        }
        
        calculateLineTotal(row);
    }
}

// Calculate total for a single line
function calculateLineTotal(row) {
    const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.price-input').value) || 0;
    const discountPercent = parseFloat(row.querySelector('.discount-input')?.value) || 0;
    
    let lineTotal = quantity * unitPrice;
    if (discountPercent > 0) {
        lineTotal = lineTotal * (1 - (discountPercent / 100));
    }
    
    const totalInput = row.querySelector('.total-input');
    if (totalInput) {
        totalInput.value = lineTotal.toFixed(2);
    }
    
    calculateTotals();
}

// Calculate all totals (subtotal, tax, total)
function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.line-item').forEach(row => {
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.price-input').value) || 0;
        const discountPercent = parseFloat(row.querySelector('.discount-input')?.value) || 0;
        
        let lineTotal = quantity * unitPrice;
        if (discountPercent > 0) {
            lineTotal = lineTotal * (1 - (discountPercent / 100));
        }
        
        // Update line total
        const totalInput = row.querySelector('.total-input');
        if (totalInput) {
            totalInput.value = lineTotal.toFixed(2);
        }
        
        subtotal += lineTotal;
    });
    
    // Higher-level Discount (Order-level)
    const discountDropdown = document.getElementById('manual_discount');
    const orderDiscountEl = document.getElementById('order_discount_amount');
    let orderDiscountAmount = 0;

    if (discountDropdown && discountDropdown.value) {
        const selectedOption = discountDropdown.options[discountDropdown.selectedIndex];
        const type = selectedOption.getAttribute('data-type');
        const value = parseFloat(selectedOption.getAttribute('data-value')) || 0;
        
        if (type === 'percentage') {
            orderDiscountAmount = subtotal * (value / 100);
        } else {
            orderDiscountAmount = value;
        }
        
        if (orderDiscountEl) orderDiscountEl.value = orderDiscountAmount.toFixed(2);
    } else {
        orderDiscountAmount = parseFloat(orderDiscountEl?.value) || 0;
    }

    // Tax calculation based on selected tax class
    let taxRate = 0;
    const taxClassSelect = document.getElementById('tax_class_id');
    if (taxClassSelect && taxClassSelect.value) {
        const selectedOption = taxClassSelect.options[taxClassSelect.selectedIndex];
        taxRate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;
    }
    const taxAmount = (subtotal - orderDiscountAmount) * taxRate;
    const total = (subtotal - orderDiscountAmount) + taxAmount;
    
    // Get currency symbol
    const currencySelect = document.getElementById('payment_currency');
    let currencySymbol = '$';
    if (currencySelect) {
        const currency = currencySelect.value;
        if (currency === 'EUR') currencySymbol = '€';
        else if (currency === 'GBP') currencySymbol = '£';
        else if (currency === 'INR') currencySymbol = '₹';
    }

    // Update summary displays
    const subtotalEl = document.getElementById('subtotal');
    const discountEl = document.getElementById('discount_display');
    const taxEl = document.getElementById('tax');
    const totalEl = document.getElementById('total');
    
    if (subtotalEl) subtotalEl.textContent = currencySymbol + subtotal.toFixed(2);
    if (discountEl) discountEl.textContent = '-' + currencySymbol + orderDiscountAmount.toFixed(2);
    if (taxEl) taxEl.textContent = currencySymbol + taxAmount.toFixed(2);
    if (totalEl) totalEl.textContent = currencySymbol + total.toFixed(2);
    
    // Update hidden inputs if they exist
    const subtotalInput = document.getElementById('subtotal_amount');
    const discountInput = document.getElementById('order_discount_amount_hidden');
    const taxInput = document.getElementById('tax_amount');
    const totalInput = document.getElementById('total_amount');
    
    if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);
    if (discountInput) discountInput.value = orderDiscountAmount.toFixed(2);
    if (taxInput) taxInput.value = taxAmount.toFixed(2);
    if (totalInput) totalInput.value = total.toFixed(2);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to existing line items
    document.querySelectorAll('.line-item').forEach(row => {
        const itemSelect = row.querySelector('.item-select');
        const quantityInput = row.querySelector('.quantity-input');
        const priceInput = row.querySelector('.price-input');
        const discountInput = row.querySelector('.discount-input');
        
        if (itemSelect) {
            itemSelect.addEventListener('change', function() {
                updateItemPrice(this);
            });
        }
        
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                calculateLineTotal(row);
            });
        }
        
        if (priceInput) {
            priceInput.addEventListener('input', function() {
                calculateLineTotal(row);
            });
        }

        if (discountInput) {
            discountInput.addEventListener('input', function() {
                calculateLineTotal(row);
            });
        }
    });

    // Order-level discount listener
    const orderDiscountEl = document.getElementById('order_discount_amount');
    if (orderDiscountEl) {
        orderDiscountEl.addEventListener('input', calculateTotals);
    }
    
    // Currency listener
    const currencySelect = document.getElementById('payment_currency');
    if (currencySelect) {
        currencySelect.addEventListener('change', calculateTotals);
    }
    
    // Global fallback for inline onchange="applyDiscount()"
    window.applyDiscount = calculateTotals;

    // Initial calculation
    calculateTotals();
});
