<div class="btn-list">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveDepositModal"
            onclick='window.setAdTopupModalData({ id: {{ $id }}, sellerName: @json($sellerName), amount: @json($amount) })'>
        {{ __('labels.complete') }}
    </button>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectDepositModal"
            onclick='window.setAdTopupModalData({ id: {{ $id }}, sellerName: @json($sellerName), amount: @json($amount) })'>
        {{ __('labels.fail') }}
    </button>
</div>
