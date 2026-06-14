document.addEventListener("DOMContentLoaded", () => {
    const grid = document.getElementById("brands-grid");
    const searchInput = document.getElementById("brands-search");
    const emptyEl = document.getElementById("brands-empty");
    const countEl = document.getElementById("brands-count");
    const classFilters = document.getElementById("brands-class-filters");
    const classHint = document.getElementById("brands-class-hint");

    if (!grid) return;

    const SEGMENT_LABELS = {
        economy: "Эконом-класс",
        family: "Семейные кроссоверы",
        business: "Бизнес-класс",
        premium: "Премиум-класс",
    };

    const priceMultipliers = {
        audi: 1.35, avatr: 1.4, baw: 1.15, bmw: 1.55, byd: 1.18, changan: 1.18, chery: 1.15, ford: 1.1,
        geely: 1.15, genesis: 1.45, gmc: 1.6, honda: 1.25, hongqi: 1.42, hyundai: 1.15, infiniti: 1.5,
        jeep: 1.35, kia: 1.12, lada: 1, "land-rover": 1.7, lexus: 1.6, lixiang: 1.5, mazda: 1.2,
        mercedes: 1.65, mini: 1.35, mitsubishi: 1.15, nissan: 1.15, "polar-stone": 1.38, porsche: 1.9,
        skoda: 1.15, subaru: 1.25, tank: 1.28, toyota: 1.2, volkswagen: 1.2, volvo: 1.55, voyah: 1.45,
        xiaomi: 1.35, zeekr: 1.45, zhiji: 1.4, dongfeng: 1.16,
    };

    let allBrands = [];
    let activeSegment = "";
    let visibleBrands = [];

    function segmentForSlug(slug) {
        const m = priceMultipliers[slug] ?? 1.2;
        if (m <= 1.18) return "economy";
        if (m <= 1.32) return "family";
        if (m <= 1.52) return "business";
        return "premium";
    }

    function logoSrc(slug) {
        return `assets/images/brands/${slug}.svg`;
    }

    function updateClassHint() {
        if (!classHint) return;
        if (!activeSegment) {
            classHint.hidden = true;
            classHint.textContent = "";
            return;
        }
        const count = visibleBrands.filter((b) => segmentForSlug(b.slug) === activeSegment).length;
        const label = SEGMENT_LABELS[activeSegment] || activeSegment;
        classHint.hidden = false;
        classHint.textContent = `${label}: выделено ${count} ${count === 1 ? "марка" : count < 5 ? "марки" : "марок"}`;
    }

    function setActiveSegmentButton(segment) {
        if (!classFilters) return;
        classFilters.querySelectorAll(".brands-class-btn").forEach((btn) => {
            const isActive = (btn.dataset.segment || "") === segment;
            btn.classList.toggle("is-active", isActive);
            btn.setAttribute("aria-selected", isActive ? "true" : "false");
        });
    }

    function applySegmentHighlight() {
        grid.querySelectorAll(".brand-card").forEach((card) => {
            const cardSegment = card.dataset.segment || "";
            const match = !activeSegment || cardSegment === activeSegment;
            card.classList.toggle("is-highlighted", Boolean(activeSegment && match));
            card.classList.toggle("is-dimmed", Boolean(activeSegment && !match));
        });
        updateClassHint();
    }

    function renderBrands(list) {
        visibleBrands = list;

        if (countEl) {
            countEl.textContent = String(list.length);
        }

        if (list.length === 0) {
            grid.innerHTML = "";
            if (emptyEl) emptyEl.hidden = false;
            updateClassHint();
            return;
        }

        if (emptyEl) emptyEl.hidden = true;

        grid.innerHTML = list
            .map((brand) => {
                const href = `brand.php?brand=${encodeURIComponent(brand.slug)}`;
                const segment = segmentForSlug(brand.slug);
                return `
                    <a class="brand-card" href="${href}" data-slug="${brand.slug}" data-segment="${segment}">
                        <span class="brand-card-media">
                            <img
                                src="${logoSrc(brand.slug)}"
                                alt="${brand.name}"
                                width="140"
                                height="48"
                                loading="lazy"
                                onerror="if(!this.dataset.triedPng){this.dataset.triedPng=1;this.src=this.src.replace(/\\.svg$/,'.png');}else{this.hidden=true;this.nextElementSibling.hidden=false;}"
                            >
                            <span class="brand-card-fallback" hidden>${brand.name}</span>
                        </span>
                        <span class="brand-card-name">${brand.name}</span>
                    </a>
                `;
            })
            .join("");

        applySegmentHighlight();
    }

    function getFilteredList() {
        const term = searchInput ? searchInput.value.trim().toLowerCase() : "";
        if (!term) return allBrands;
        return allBrands.filter((b) => b.name.toLowerCase().includes(term) || b.slug.includes(term));
    }

    function refreshBrands() {
        renderBrands(getFilteredList());
    }

    function selectSegment(segment) {
        activeSegment = segment;
        setActiveSegmentButton(segment);
        refreshBrands();
        const url = new URL(window.location.href);
        if (segment) {
            url.searchParams.set("segment", segment);
        } else {
            url.searchParams.delete("segment");
        }
        window.history.replaceState({}, "", url);
    }

    fetch("assets/data/brands.json", { cache: "no-store" })
        .then((res) => {
            if (!res.ok) throw new Error("Не удалось загрузить список марок");
            return res.json();
        })
        .then((data) => {
            allBrands = Array.isArray(data.brands) ? data.brands : [];
            allBrands.sort((a, b) => a.name.localeCompare(b.name, "ru"));

            const params = new URLSearchParams(window.location.search);
            const fromUrl = (params.get("segment") || "").trim().toLowerCase();
            if (fromUrl && SEGMENT_LABELS[fromUrl]) {
                activeSegment = fromUrl;
                setActiveSegmentButton(fromUrl);
            }

            refreshBrands();
        })
        .catch(() => {
            grid.innerHTML = `<p class="brands-error">Не удалось загрузить каталог марок. Обновите страницу.</p>`;
        });

    if (searchInput) {
        searchInput.addEventListener("input", refreshBrands);
    }

    if (classFilters) {
        classFilters.addEventListener("click", (event) => {
            const btn = event.target.closest(".brands-class-btn");
            if (!btn) return;
            selectSegment(btn.dataset.segment || "");
        });
    }
});
