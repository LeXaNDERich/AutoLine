document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);

    const brand = (params.get("brand") || "Марка").trim();
    const brandSlug = (params.get("brandSlug") || "").trim();
    const part = (params.get("part") || "Запчасть").trim();
    const price = (params.get("price") || "Цена по запросу").trim();
    const meta = (params.get("meta") || "Наличие уточняйте у менеджера").trim();
    const image = (params.get("image") || "").trim();

    const titleEl = document.getElementById("part-title");
    const brandEl = document.getElementById("part-brand");
    const priceEl = document.getElementById("part-price");
    const metaEl = document.getElementById("part-meta");
    const imageEl = document.getElementById("part-image");
    const brandLink = document.getElementById("part-brand-link");
    const backLink = document.getElementById("part-back-link");

    document.title = `${part} | ${brand} | AutoLine`;

    if (titleEl) titleEl.textContent = part;
    if (brandEl) brandEl.textContent = brand;
    if (priceEl) priceEl.textContent = `от ${price}`;
    if (metaEl) metaEl.textContent = meta;

    if (imageEl) {
        imageEl.src = image || "assets/images/car-placeholder.svg";
        imageEl.alt = `${part} ${brand}`;
        imageEl.onerror = () => {
            imageEl.onerror = null;
            imageEl.src = "assets/images/car-placeholder.svg";
        };
    }

    const brandHref = brandSlug ? `brand.php?brand=${encodeURIComponent(brandSlug)}` : "brands.php";
    if (brandLink) {
        brandLink.href = brandHref;
        brandLink.textContent = brand;
    }
    if (backLink) {
        backLink.href = brandHref;
    }
});
