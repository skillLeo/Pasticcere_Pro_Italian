@extends('frontend.layouts.app')

@section('title', 'Invoice Preview - Edit Before Saving')

@section('content')
<div class="container py-5 px-md-5">

    <div class="card border-warning shadow-sm">
        <div class="card-header" style="background-color: #041930;">
            <h5 class="mb-0 fw-bold" style="color: #e2ae76;">
                <i class="bi bi-eye me-2"></i>
                Invoice Preview - Review & Edit
            </h5>
        </div>

        <div class="card-body">
            <!-- Invoice Info -->
            <div class="alert alert-info">
                <strong>Invoice ID:</strong> {{ $invoice->id }} <br>
                <strong>Uploaded:</strong> {{ $invoice->created_at->format('Y-m-d H:i') }} <br>
                <strong>Status:</strong> <span class="badge bg-success">{{ ucfirst($invoice->status) }}</span>
            </div>

            <!-- Extracted Items Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead style="background-color: #e2ae76; color: #041930;">
                        <tr>
                            <th>Ingredient Name</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Divider</th>
                            <th>Price/kg</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr data-item-id="{{ $item->id }}">
                            <td>
                                <input type="text" 
                                       class="form-control ingredient-name" 
                                       value="{{ $item->ingredient_name }}"
                                       data-original="{{ $item->ingredient_name }}">
                                <small class="text-muted">Normalized: {{ $item->normalized_name }}</small>
                            </td>
                            <td>€{{ number_format($item->price, 2) }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->unit }}</td>
                            <td>
                                <input type="number" 
                                       class="form-control divider-input" 
                                       value="{{ $item->divider }}" 
                                       step="0.01" 
                                       min="0.01"
                                       style="width: 100px;">
                            </td>
                            <td class="price-per-kg">€{{ number_format($item->price_per_kg, 2) }}</td>
                            <td>
                                @if($item->is_new)
                                    <span class="badge bg-success">New</span>
                                @else
                                    <span class="badge bg-warning">Update</span>
                                    <br><small>{{ $item->existingIngredient->ingredient_name }}</small>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary update-item">
                                    <i class="bi bi-check"></i> Update
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end"><strong>Total Cost:</strong></td>
                            <td colspan="3"><strong>€{{ number_format($invoice->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="text-end mt-4">
                <a href="{{ route('ingredients.index') }}" class="btn btn-secondary btn-lg me-2">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
                <button id="saveAllBtn" class="btn btn-lg" style="background-color: #e2ae76; color: #041930;">
                    <i class="bi bi-save2 me-2"></i> Save All to Database
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Update individual item
    document.querySelectorAll('.update-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const itemId = row.dataset.itemId;
            const divider = row.querySelector('.divider-input').value;
            const ingredientName = row.querySelector('.ingredient-name').value;

            fetch(`/invoices/items/${itemId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    divider: divider,
                    ingredient_name: ingredientName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.querySelector('.price-per-kg').textContent = '€' + data.price_per_kg;
                    alert('Item updated successfully!');
                }
            });
        });
    });

    // Save all to database
    document.getElementById('saveAllBtn').addEventListener('click', function() {
        if (!confirm('Save all items to database? This will update ingredients and create a cost record.')) {
            return;
        }

        this.disabled = true;
        this.innerHTML = '<spanclass="spinner-border spinner-border-sm me-2"></spanclass=>Saving...';
        fetch('{{ route("invoices.save", $invoice->id) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = data.redirect_url;
        } else {
            alert('Error: ' + data.message);
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save2 me-2"></i> Save All to Database';
        }
    });
});});
</script>
@endsection
