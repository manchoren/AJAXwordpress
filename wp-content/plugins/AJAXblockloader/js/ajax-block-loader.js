document.addEventListener("DOMContentLoaded", () => {
    const contentContainer = document.querySelector(ajaxBlockLoader.content_selector);

    if (!contentContainer) {
        console.error(`No se encontró el contenedor usando el selector: ${ajaxBlockLoader.content_selector}`);
        return;
    }

    document.body.addEventListener("click", (e) => {
        const link = e.target.closest("a");
        if (link && link.hostname === window.location.hostname) {
            e.preventDefault();
            const url = link.href;

            // Actualizar contenido mediante AJAX
            fetchContent(url);
            history.pushState({}, "", url);
        }
    });

    window.addEventListener("popstate", () => {
        fetchContent(window.location.href);
    });

    function fetchContent(url) {
        // Mostrar mensaje de carga
        contentContainer.innerHTML = ajaxBlockLoader.loading_message;

        fetch(ajaxBlockLoader.ajax_url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `action=load_blocks&url=${encodeURIComponent(url)}`,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    contentContainer.innerHTML = data.data.content;
                } else {
                    contentContainer.innerHTML = ajaxBlockLoader.error_message;
                    console.error(data.message);
                }
            })
            .catch((error) => {
                contentContainer.innerHTML = ajaxBlockLoader.network_error_message;
                console.error("Error:", error);
            });
    }
});
