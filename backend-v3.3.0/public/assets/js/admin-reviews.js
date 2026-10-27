document.addEventListener('DOMContentLoaded', function () {
    const tableElement = $('#admin-reviews-table');

    const attachReviewFilters = function () {
        if (!$.fn.DataTable.isDataTable(tableElement)) {
            window.setTimeout(attachReviewFilters, 100);
            return;
        }

        const table = tableElement.DataTable();
        const refreshButton = document.getElementById('refresh');

        refreshButton?.addEventListener('click', function () {
            table.ajax.reload(null, false);
        });

        ['#ratingFilter', '#rangeFilter', '#imageFilter'].forEach(function (selector) {
            const element = document.querySelector(selector);
            element?.addEventListener('change', function () {
                table.ajax.reload(null, false);
            });
        });

        table.on('preXhr.dt', function (e, settings, data) {
            data.rating_filter = document.getElementById('ratingFilter')?.value || '';
            data.date_range_filter = document.getElementById('rangeFilter')?.value || 'all';
            data.image_filter = document.getElementById('imageFilter')?.value || 'all';
        });
    };

    attachReviewFilters();
});

document.addEventListener('click', function (event) {
    const deleteButton = event.target.closest('.delete-admin-review');
    if (!deleteButton) {
        return;
    }

    const deleteUrl = deleteButton.getAttribute('data-delete-url');
    const confirmationText = deleteButton.getAttribute('data-delete-title') || 'You are about to delete this review.';

    Swal.fire({
        title: 'Are you sure?',
        html: confirmationText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: primaryColor,
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to delete review.');
                }

                return response.json();
            })
            .then((data) => {
                if (!data.success) {
                    return Swal.fire('Error!', data.message, 'error');
                }

                $('.data-table').DataTable().ajax.reload(null, false);
                return Swal.fire('Deleted!', data.message, 'success');
            })
            .catch(() => {
                Swal.fire('Error!', 'There was a problem deleting the review.', 'error');
            });
    });
});
