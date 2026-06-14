(function () {
    function initAuthForms() {
        const api = window.AutoLinePhone;
        if (!api) {
            return;
        }

        api.refreshAll();

        document.querySelectorAll('input[type="email"]').forEach((input) => {
            if (input.dataset.authEmailValidate === "1") {
                return;
            }
            input.dataset.authEmailValidate = "1";
            input.addEventListener("blur", () => {
                const form = input.closest("form");
                const isRegister = form && form.querySelector('input[name="action"][value="register"]');
                const phoneInput = form && form.querySelector('input[name="phone"]');
                const phoneDigits = phoneInput ? phoneInput.value.replace(/\D/g, "") : "";
                const phoneFilled = phoneDigits.length > 1;
                const required = Boolean(isRegister) || (!phoneFilled && input.value.trim() !== "");
                if (input.offsetParent !== null) {
                    api.validateEmailField(input, required);
                }
            });
        });

        function validateLoginForm(form) {
            const phoneInput = form.querySelector('input[name="phone"]');
            const emailInput = form.querySelector('input[name="email"]');
            const passwordInput = form.querySelector('input[name="password"]');
            let ok = true;

            [phoneInput, emailInput, passwordInput].forEach((el) => {
                if (el) api.clearFieldError(el);
            });

            const phone = phoneInput ? phoneInput.value.trim() : "";
            const email = emailInput ? emailInput.value.trim() : "";
            const password = passwordInput ? passwordInput.value : "";
            const phoneDigits = phone.replace(/\D/g, "");

            if (phoneDigits.length > 1 && !api.isPhoneComplete(phone)) {
                api.setFieldError(phoneInput, "Введите номер полностью: +7 (XXX) XXX-XX-XX");
                ok = false;
            }

            if (!phoneDigits.length && !email) {
                api.setFieldError(phoneInput, "Введите телефон или email");
                api.setFieldError(emailInput, "Введите телефон или email");
                ok = false;
            } else if (email && !api.isEmailValid(email)) {
                api.validateEmailField(emailInput, true);
                ok = false;
            }

            if (!password) {
                api.setFieldError(passwordInput, "Введите пароль");
                ok = false;
            }

            return ok;
        }

        function validateRegisterForm(form) {
            const phoneInput = form.querySelector('input[name="phone"]');
            const emailInput = form.querySelector('input[name="email"]');
            const nameInput = form.querySelector('input[name="name"]');
            const passwordInput = form.querySelector('input[name="password"]');
            const password2Input = form.querySelector('input[name="password2"]');
            let ok = true;

            [phoneInput, emailInput, nameInput, passwordInput, password2Input].forEach((el) => {
                if (el) api.clearFieldError(el);
            });

            if (!api.validatePhoneField(phoneInput)) {
                ok = false;
            }
            if (!api.validateEmailField(emailInput, true)) {
                ok = false;
            }
            if (nameInput && !nameInput.value.trim()) {
                api.setFieldError(nameInput, "Введите имя");
                ok = false;
            }
            const pass = passwordInput ? passwordInput.value : "";
            const pass2 = password2Input ? password2Input.value : "";
            if (!pass || pass.length < 6) {
                api.setFieldError(passwordInput, "Пароль не короче 6 символов");
                ok = false;
            }
            if (pass !== pass2) {
                api.setFieldError(password2Input, "Пароли должны совпадать");
                ok = false;
            }

            return ok;
        }

        document.querySelectorAll("form").forEach((form) => {
            const actionInput = form.querySelector('input[name="action"]');
            if (!actionInput || form.dataset.authBound === "1") {
                return;
            }
            form.dataset.authBound = "1";
            form.addEventListener("submit", (event) => {
                const action = actionInput.value;
                let valid = true;
                if (action === "login") {
                    valid = validateLoginForm(form);
                } else if (action === "register") {
                    valid = validateRegisterForm(form);
                }
                if (!valid) {
                    event.preventDefault();
                }
            });
        });

        const loginCard = document.getElementById("loginCard");
        const registerCard = document.getElementById("registerCard");
        const toggleRegister = document.getElementById("toggleRegister");
        const toggleRegisterWrap = document.getElementById("toggleRegisterWrap");
        const backToLogin = document.getElementById("backToLogin");

        if (toggleRegister && loginCard && registerCard) {
            toggleRegister.addEventListener("click", () => {
                loginCard.style.display = "none";
                registerCard.style.display = "block";
                if (toggleRegisterWrap) toggleRegisterWrap.style.display = "none";
                api.refreshAll(registerCard);
            });
        }

        if (backToLogin && loginCard && registerCard) {
            backToLogin.addEventListener("click", () => {
                registerCard.style.display = "none";
                loginCard.style.display = "block";
                if (toggleRegisterWrap) toggleRegisterWrap.style.display = "block";
                api.refreshAll(loginCard);
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAuthForms);
    } else {
        initAuthForms();
    }

    window.addEventListener("load", () => {
        if (window.AutoLinePhone) {
            AutoLinePhone.refreshAll();
        }
    });
})();
