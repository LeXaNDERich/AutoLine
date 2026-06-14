(function () {
    function digitsOnly(value) {
        return String(value || "").replace(/\D/g, "");
    }

    function normalizeRuDigits(value) {
        let d = digitsOnly(value);
        if (d.startsWith("8") && d.length > 1) {
            d = "7" + d.slice(1);
        }
        if (d.length > 0 && !d.startsWith("7")) {
            d = "7" + d;
        }
        return d.slice(0, 11);
    }

    function formatRuPhone(value) {
        const d = normalizeRuDigits(value);
        if (d.length <= 1) {
            return "";
        }
        const local = d.slice(1);
        let out = "+7 (" + local.slice(0, 3);
        if (local.length < 3) {
            return out;
        }
        out += ") " + local.slice(3, 6);
        if (local.length < 6) {
            return out;
        }
        out += "-" + local.slice(6, 8);
        if (local.length < 8) {
            return out;
        }
        out += "-" + local.slice(8, 10);
        return out;
    }

    function countDigitsBefore(str, pos) {
        let count = 0;
        const limit = Math.max(0, Math.min(pos, str.length));
        for (let i = 0; i < limit; i++) {
            if (/\d/.test(str[i])) {
                count++;
            }
        }
        return count;
    }

    function cursorAfterDigitIndex(formatted, digitIndex) {
        if (digitIndex <= 0) {
            return 0;
        }
        let count = 0;
        for (let i = 0; i < formatted.length; i++) {
            if (/\d/.test(formatted[i])) {
                count++;
                if (count === digitIndex) {
                    let pos = i + 1;
                    while (pos < formatted.length && !/\d/.test(formatted[pos])) {
                        pos++;
                    }
                    return pos;
                }
            }
        }
        return formatted.length;
    }

    function setPhoneValue(input, digits, cursorDigitIndex) {
        const formatted = formatRuPhone(digits);
        input.value = formatted;
        if (!formatted) {
            return;
        }
        const pos = cursorAfterDigitIndex(formatted, cursorDigitIndex);
        requestAnimationFrame(() => {
            input.setSelectionRange(pos, pos);
        });
    }

    function isPhoneComplete(value) {
        const d = normalizeRuDigits(value);
        return d.length === 11 && d.startsWith("7");
    }

    function isEmailValid(value) {
        const v = String(value || "").trim();
        if (!v) {
            return false;
        }
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    }

    function clearFieldError(input) {
        if (!input) {
            return;
        }
        input.classList.remove("field-invalid");
        input.removeAttribute("aria-invalid");
        const hint = input.parentElement && input.parentElement.querySelector(".field-hint");
        if (hint) {
            hint.remove();
        }
    }

    function setFieldError(input, message) {
        if (!input) {
            return;
        }
        input.classList.add("field-invalid");
        input.setAttribute("aria-invalid", "true");
        let hint = input.parentElement && input.parentElement.querySelector(".field-hint");
        if (!hint && input.parentElement) {
            hint = document.createElement("span");
            hint.className = "field-hint";
            input.parentElement.appendChild(hint);
        }
        if (hint) {
            hint.textContent = message;
        }
    }

    function validatePhoneField(input) {
        if (!input || input.offsetParent === null) {
            return true;
        }
        const value = input.value.trim();
        if (!value) {
            setFieldError(input, "Введите телефон");
            return false;
        }
        if (!isPhoneComplete(value)) {
            setFieldError(input, "Введите номер полностью: +7 (XXX) XXX-XX-XX");
            return false;
        }
        clearFieldError(input);
        return true;
    }

    function validateEmailField(input, required) {
        if (!input || input.offsetParent === null) {
            return true;
        }
        const value = input.value.trim();
        if (!value) {
            if (required) {
                setFieldError(input, "Введите email");
                return false;
            }
            clearFieldError(input);
            return true;
        }
        if (!isEmailValid(value)) {
            setFieldError(input, "Email введен некорректно");
            return false;
        }
        clearFieldError(input);
        return true;
    }

    function isOptionalPhoneInput(input) {
        if (!input) {
            return false;
        }
        const form = input.closest("form");
        if (!form) {
            return false;
        }
        const action = form.querySelector('input[name="action"]');
        return Boolean(action && action.value === "login");
    }

    function removeDigitsRange(digits, fromDigit, toDigit) {
        const chars = digits.split("");
        chars.splice(fromDigit, toDigit - fromDigit);
        return chars.join("");
    }

    function bindPhoneMask(input, options) {
        if (!input || input.dataset.phoneMask === "1") {
            return;
        }
        input.dataset.phoneMask = "1";
        const optional = options && options.optional === true ? true : isOptionalPhoneInput(input);

        if (input.value.trim()) {
            input.value = formatRuPhone(input.value);
        }

        input.addEventListener("keydown", (event) => {
            const start = input.selectionStart || 0;
            const end = input.selectionEnd || 0;
            let digits = normalizeRuDigits(input.value);
            const digitBefore = countDigitsBefore(input.value, start);

            if (event.key === "Backspace" || event.key === "Delete") {
                event.preventDefault();
                if (start !== end) {
                    const from = countDigitsBefore(input.value, start);
                    const to = countDigitsBefore(input.value, end);
                    digits = removeDigitsRange(digits, from, to);
                    if (digits.length <= 1) {
                        input.value = "";
                        return;
                    }
                    setPhoneValue(input, digits, from);
                    return;
                }

                if (event.key === "Backspace") {
                    if (digitBefore <= 1) {
                        input.value = "";
                        return;
                    }
                    digits = removeDigitsRange(digits, digitBefore - 1, digitBefore);
                } else {
                    if (digitBefore >= digits.length) {
                        return;
                    }
                    digits = removeDigitsRange(digits, digitBefore, digitBefore + 1);
                }

                if (digits.length <= 1) {
                    input.value = "";
                    return;
                }
                const cursorDigit = event.key === "Backspace" ? digitBefore - 1 : digitBefore;
                setPhoneValue(input, digits, cursorDigit);
                return;
            }

            if (/^\d$/.test(event.key)) {
                event.preventDefault();
                if (digits.length >= 11) {
                    return;
                }
                let insertAt = digitBefore;
                if (start !== end) {
                    const to = countDigitsBefore(input.value, end);
                    digits = removeDigitsRange(digits, insertAt, to);
                }
                const chars = digits.split("");
                chars.splice(insertAt, 0, event.key);
                digits = chars.join("").slice(0, 11);
                setPhoneValue(input, digits, insertAt + 1);
            }
        });

        input.addEventListener("input", () => {
            const digits = normalizeRuDigits(input.value);
            if (!digits) {
                input.value = "";
                return;
            }
            setPhoneValue(input, digits, digits.length);
        });

        input.addEventListener("paste", (event) => {
            event.preventDefault();
            const text = (event.clipboardData || window.clipboardData).getData("text");
            const digits = normalizeRuDigits(text);
            if (!digits || digits.length <= 1) {
                input.value = "";
                return;
            }
            setPhoneValue(input, digits, digits.length);
        });

        input.addEventListener("click", () => {
            const pos = input.selectionStart || 0;
            const digitIdx = countDigitsBefore(input.value, pos);
            const newPos = cursorAfterDigitIndex(input.value, Math.max(1, digitIdx));
            if (newPos !== pos) {
                input.setSelectionRange(newPos, newPos);
            }
        });

        input.addEventListener("blur", () => {
            const d = normalizeRuDigits(input.value);
            if (d.length <= 1) {
                input.value = "";
                if (optional) {
                    clearFieldError(input);
                } else {
                    validatePhoneField(input);
                }
                return;
            }
            input.value = formatRuPhone(d);
            if (optional && !isPhoneComplete(input.value)) {
                setFieldError(input, "Введите номер полностью: +7 (XXX) XXX-XX-XX");
                return;
            }
            if (!optional || isPhoneComplete(input.value)) {
                validatePhoneField(input);
            }
        });
    }

    function bindAll(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[type="tel"]').forEach((el) => bindPhoneMask(el));
    }

    function refreshAll(root) {
        bindAll(root);
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[type="tel"]').forEach((input) => {
            if (input.value.trim()) {
                input.value = formatRuPhone(input.value);
            }
        });
    }

    window.AutoLinePhone = {
        formatRuPhone,
        isPhoneComplete,
        isEmailValid,
        validatePhoneField,
        validateEmailField,
        clearFieldError,
        setFieldError,
        bindPhoneMask,
        bindAll,
        refreshAll,
    };
})();
