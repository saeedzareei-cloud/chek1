// توابع عمومی

// فرمت کردن اعداد به صورت قیمت
function formatPrice(input) {
    let value = input.value.replace(/,/g, '');
    if (!isNaN(value) && value != '') {
        input.value = Number(value).toLocaleString();
    }
}

// اعتبارسنجی کد ملی
function validateNationalCode(code) {
    if (!/^\d{10}$/.test(code)) return false;
    
    let check = parseInt(code[9]);
    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(code[i]) * (10 - i);
    }
    let remainder = sum % 11;
    
    return (remainder < 2 && check == remainder) || (remainder >= 2 && check == 11 - remainder);
}

// اعتبارسنجی شماره موبایل
function validateMobile(mobile) {
    return /^09[0-9]{9}$/.test(mobile);
}

// نمایش پیام
function showMessage(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// درخواست AJAX
async function ajaxRequest(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
        showMessage('خطا در ارتباط با سرور', 'danger');
        return null;
    }
}

// دانلود فایل
function downloadFile(url, filename) {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// چاپ صفحه
function printPage() {
    window.print();
}

// کپی به کلیپبورد
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showMessage('کپی شد', 'success');
    }).catch(() => {
        showMessage('خطا در کپی', 'danger');
    });
}

// تایید عملیات
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// مقداردهی اولیه
document.addEventListener('DOMContentLoaded', function() {
    // فرمت خودکار قیمت‌ها
    document.querySelectorAll('.price-format').forEach(input => {
        input.addEventListener('input', function() {
            formatPrice(this);
        });
    });
    
    // اعتبارسنجی کد ملی
    document.querySelectorAll('.national-code').forEach(input => {
        input.addEventListener('blur', function() {
            if (!validateNationalCode(this.value) && this.value.length === 10) {
                showMessage('کد ملی نامعتبر است', 'warning');
            }
        });
    });
    
    // اعتبارسنجی موبایل
    document.querySelectorAll('.mobile').forEach(input => {
        input.addEventListener('blur', function() {
            if (!validateMobile(this.value) && this.value.length === 11) {
                showMessage('شماره موبایل نامعتبر است', 'warning');
            }
        });
    });
});

// ------------------------------------------------------------
// بهبود ورود مبلغ: جداکننده سه‌رقمی هنگام تایپ + پاکسازی هنگام ارسال فرم
// ------------------------------------------------------------
(function () {
    function normalizeDigits(str) {
        if (str == null) return '';
        str = String(str);
        const persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        const arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        for (let i = 0; i < 10; i++) {
            str = str.replaceAll(persian[i], String(i)).replaceAll(arabic[i], String(i));
        }
        return str;
    }

    function formatThousands(value) {
        value = normalizeDigits(value).replace(/,/g, '').replace(/[^\d]/g, '');
        if (value === '') return '';
        return Number(value).toLocaleString();
    }

    function stripThousands(value) {
        return normalizeDigits(value).replace(/,/g, '');
    }

    // فرمت هنگام تایپ (cursor را به انتها می‌برد)
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!el) return;
        if (el.classList && (el.classList.contains('money-input') || el.classList.contains('price-format'))) {
            const formatted = formatThousands(el.value);
            if (formatted !== el.value) {
                el.value = formatted;
                try { el.setSelectionRange(el.value.length, el.value.length); } catch (_) {}
            }
        }
    });

    // پاکسازی هنگام ارسال فرم (تا دیتابیس عدد خام ذخیره کند)
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.querySelectorAll) return;
        const inputs = form.querySelectorAll('input.money-input, input.price-format');
        inputs.forEach(function (inp) {
            inp.value = stripThousands(inp.value);
        });
    });
})();
