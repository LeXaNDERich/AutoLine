(function () {
    const models = Array.isArray(window.AUTOLINE_PARTS_MODELS) ? window.AUTOLINE_PARTS_MODELS : [];

    function brandKey(slug) {
        return String(slug || "")
            .trim()
            .toLowerCase()
            .replace(/-/g, "");
    }

    function modelsForBrand(brandSlug) {
        const key = brandKey(brandSlug);
        if (!key) return [];
        return models.filter((m) => brandKey(m.brand_slug) === key);
    }

    function fillModelSelect(modelSelect, brandSlug, selectedSlug, allLabel) {
        if (!modelSelect) return;
        modelSelect.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = brandSlug ? allLabel || "Все модели марки" : "Сначала выберите марку";
        modelSelect.appendChild(placeholder);

        modelsForBrand(brandSlug).forEach((m) => {
            const opt = document.createElement("option");
            opt.value = m.slug;
            opt.textContent = m.name;
            if (m.slug === selectedSlug) {
                opt.selected = true;
            }
            modelSelect.appendChild(opt);
        });

        modelSelect.disabled = !brandSlug;
    }

    document.querySelectorAll("[data-parts-picker]").forEach((picker) => {
        const brandSelect = picker.querySelector("[data-parts-brand-select]");
        const modelSelect = picker.querySelector("[data-parts-model-select]");
        if (!brandSelect || !modelSelect) return;

        const initialBrand = picker.dataset.initialBrand || "";
        const initialModel = picker.dataset.initialModel || "";

        if (initialBrand) {
            brandSelect.value = initialBrand;
        }

        fillModelSelect(modelSelect, brandSelect.value, initialModel, "Выберите модель");

        brandSelect.addEventListener("change", () => {
            fillModelSelect(modelSelect, brandSelect.value, "", "Выберите модель");
        });
    });

    document.querySelectorAll("[data-parts-filter-form]").forEach((form) => {
        const brandSelect = form.querySelector("[data-parts-filter-brand]");
        const modelSelect = form.querySelector("[data-parts-filter-model]");
        if (!brandSelect || !modelSelect) return;

        const initialBrand = form.dataset.initialBrand || "";
        const initialModel = form.dataset.initialModel || "";

        if (initialBrand) {
            brandSelect.value = initialBrand;
        }

        fillModelSelect(modelSelect, brandSelect.value, initialModel, "Все модели марки");

        brandSelect.addEventListener("change", () => {
            fillModelSelect(modelSelect, brandSelect.value, "", "Все модели марки");
        });
    });

    document.querySelectorAll("[data-model-edit-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const block = btn.closest(".account-model-block");
            if (!block) return;
            const form = block.querySelector(".account-model-edit");
            if (!form) return;
            const open = form.hasAttribute("hidden");
            if (open) {
                form.removeAttribute("hidden");
                btn.setAttribute("aria-expanded", "true");
            } else {
                form.setAttribute("hidden", "");
                btn.setAttribute("aria-expanded", "false");
            }
        });
    });
})();
