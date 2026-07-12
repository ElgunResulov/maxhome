(function () {
    var STORAGE_KEY = 'maxhome_compare_pending';
    var toastTimer = null;

    function readPending() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.slug) {
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function writePending(product) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(product));
    }

    function clearPending() {
        localStorage.removeItem(STORAGE_KEY);
    }

    function ensureToast() {
        var toast = document.getElementById('compare-toast');
        if (toast) {
            return toast;
        }
        toast = document.createElement('div');
        toast.id = 'compare-toast';
        toast.className = 'compare-toast';
        toast.hidden = true;
        document.body.appendChild(toast);
        return toast;
    }

    function showToast(message) {
        var toast = ensureToast();
        toast.textContent = message;
        toast.hidden = false;
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(function () {
            toast.hidden = true;
        }, 3200);
    }

    function markActiveButton(slug) {
        document.querySelectorAll('.js-product-compare-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.productSlug === slug);
        });
    }

    function handleCompareClick(event) {
        var btn = event.currentTarget;
        if (!(btn instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        var product = {
            id: btn.dataset.productId || '',
            slug: btn.dataset.productSlug || '',
            name: btn.dataset.productName || ''
        };

        if (!product.slug) {
            return;
        }

        var pending = readPending();
        if (!pending) {
            writePending(product);
            markActiveButton(product.slug);
            showToast('Müqayisə üçün ilk məhsul seçildi. İndi ikinci məhsulu seçin.');
            return;
        }

        if (pending.slug === product.slug) {
            showToast('Eyni məhsulu seçdiniz. Başqa məhsul seçin.');
            return;
        }

        clearPending();
        markActiveButton('');
        window.location.href = 'product_compare.php?a=' + encodeURIComponent(pending.slug) + '&b=' + encodeURIComponent(product.slug);
    }

    function restorePendingState() {
        var pending = readPending();
        if (pending && pending.slug) {
            markActiveButton(pending.slug);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-product-compare-btn').forEach(function (btn) {
            btn.addEventListener('click', handleCompareClick);
        });
        restorePendingState();
    });
})();
