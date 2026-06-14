import { defineConfig } from "vite";
import { createProxyMiddleware } from "http-proxy-middleware";

export default defineConfig({
    server: {
        host: true,
        port: 5173,
        strictPort: true,
        proxy: {
            // Чтобы форма и каталог запчастей продолжали работать через PHP в OSPanel.
            "/submit.php": {
                target: "http://autoline",
                changeOrigin: true,
            },
            "/parts-data.php": {
                target: "http://autoline",
                changeOrigin: true,
            },
        },
    },
    plugins: [
        {
            name: "php-proxy",
            configureServer(server) {
                // Важно: Vite по умолчанию отдаёт .php как статический файл.
                // Проксируем ВСЕ запросы к *.php обратно в OSPanel, чтобы PHP исполнялся.
                server.middlewares.use((req, res, next) => {
                    const url = req.url || "";
                    const pathname = url.split("?")[0];
                    if (!pathname.toLowerCase().endsWith(".php")) {
                        next();
                        return;
                    }

                    const proxy = createProxyMiddleware({
                        target: "http://autoline",
                        changeOrigin: true,
                    });

                    return proxy(req, res, next);
                });
            },
        },
    ],
});

