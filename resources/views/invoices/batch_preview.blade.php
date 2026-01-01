@extends('frontend.layouts.app')

@section('title', 'Batch Invoice Preview')

@section('content')
<div class="container py-5 px-md-5">

  <div class="card border-warning shadow-sm">
    <div class="card-header" style="background-color:#041930;">
      <h5 class="mb-0 fw-bold" style="color:#e2ae76;">
        <i class="bi bi-collection me-2"></i>
        Batch Preview - Review & Edit (Batch #{{ $batch->id }})
      </h5>
    </div>

    <div class="card-body">

      <div class="alert alert-info">
        <strong>Files:</strong> {{ $batch->invoices->count() }} <br>
        <strong>Status:</strong> <span class="badge bg-success">{{ ucfirst($batch->status) }}</span> <br>
        <strong>Batch Total:</strong> €{{ number_format((float)($batch->total_amount ?? 0), 2) }}
      </div>

      @foreach($batch->invoices as $invoice)
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong>Invoice #{{ $invoice->id }}</strong>
              <span class="ms-2 badge bg-secondary">{{ strtoupper($invoice->file_type) }}</span>
              <span class="ms-2 text-muted">{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div>
              <strong>Total:</strong> €{{ number_format((float)($invoice->total_amount ?? 0), 2) }}
            </div>
          </div>

          <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
              <thead style="background-color:#e2ae76; color:#041930;">
                <tr>
                  <th>Ingredient</th>
                  <th>Price</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Divider</th>
                  <th>Price/kg</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
              @foreach($invoice->items as $item)
                <tr data-item-id="{{ $item->id }}">
                  <td>
                    <input type="text" class="form-control ingredient-name" value="{{ $item->ingredient_name }}">
                    <small class="text-muted">Normalized: {{ $item->normalized_name }}</small>
                  </td>
                  <td>€{{ number_format((float)$item->price, 2) }}</td>
                  <td>{{ $item->quantity }}</td>
                  <td>{{ $item->unit }}</td>
                  <td>
                    <input type="number" class="form-control divider-input" value="{{ $item->divider }}"
                           step="0.01" min="0.01" style="width:100px;">
                  </td>
                  <td class="price-per-kg">€{{ number_format((float)$item->price_per_kg, 2) }}</td>
                  <td>
                    @if($item->is_new)
                      <span class="badge bg-success">New</span>
                    @else
                      <span class="badge bg-warning">Update</span>
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
            </table>
          </div>
        </div>
      @endforeach

      <div class="text-end mt-3">
        <a href="{{ route('ingredients.index') }}" class="btn btn-secondary btn-lg me-2">
          <i class="bi bi-x-circle"></i> Cancel
        </a>

        <button id="saveBatchBtn" class="btn btn-lg" style="background-color:#e2ae76; color:#041930;">
          <i class="bi bi-save2 me-2"></i> Save All Invoices
        </button>
      </div>

    </div>
  </div>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

  // Update item
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
        body: JSON.stringify({ divider, ingredient_name: ingredientName })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          row.querySelector('.price-per-kg').textContent = '€' + data.price_per_kg;
          alert('Item updated!');
        } else {
          alert('Error: ' + (data.message || 'Update failed'));
        }
      });
    });
  });

  // Save batch
  document.getElementById('saveBatchBtn').addEventListener('click', function() {
    if (!confirm('Save ALL invoices to database? This will update ingredients and create costs.')) return;

    this.disabled = true;

    fetch(`{{ route('invoices.batch.save', $batch->id) }}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        window.location.href = data.redirect_url;
      } else {
        alert('Error: ' + data.message);
        this.disabled = false;
      }
    })
    .catch(() => {
      alert('Failed to save batch.');
      this.disabled = false;
    });
  });

});
</script>
@endsection
