document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);

    const brand = (params.get("brand") || "Марка").trim();
    const model = (params.get("model") || "Модель").trim();
    const price = (params.get("price") || "Цена по запросу").trim();
    const image = (params.get("image") || "").trim();

    const titleEl = document.getElementById("car-title");
    const brandEl = document.getElementById("car-brand");
    const priceEl = document.getElementById("car-price");
    const imageEl = document.getElementById("car-image");
    const brandLink = document.getElementById("car-brand-link");
    const backLink = document.getElementById("car-back-link");
    const contactLink = document.getElementById("car-contact-link");

    document.title = `${brand} ${model} | AutoLine`;

    if (titleEl) titleEl.textContent = model;
    if (brandEl) brandEl.textContent = brand;
    if (priceEl) priceEl.textContent = `от ${price}`;
    if (imageEl) {
        imageEl.src = image || "https://source.unsplash.com/1280x760/?car";
        imageEl.alt = `${brand} ${model}`;
        imageEl.onerror = () => {
            imageEl.onerror = null;
            imageEl.src = "assets/images/car-placeholder.svg";
        };
    }

    const brandSlug = params.get("brandSlug");
    const brandPageHref = brandSlug
        ? `brand.php?brand=${encodeURIComponent(brandSlug)}`
        : "brands.php";

    if (brandLink) {
        brandLink.href = brandPageHref;
        brandLink.textContent = brand;
    }

    if (backLink) {
        backLink.href = brandPageHref;
    }

    if (contactLink) {
        contactLink.href = `index.php?brand=${encodeURIComponent(brand)}#contact`;
    }
});
