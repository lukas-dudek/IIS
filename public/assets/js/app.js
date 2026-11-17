document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('form[novalidate]');
    Array.prototype.forEach.call(forms, function (form) {
        var invalid = form.querySelector('.is-invalid');
        if (invalid) {
            invalid.focus();
        }
    });
});
