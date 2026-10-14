document.querySelectorAll(".password-toggle").forEach(button => {
    const inputId = button.getAttribute("aria-controls");
    const passwordInput = inputId ? document.getElementById(inputId) : null;

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    button.addEventListener("click", () => {
        const isVisible = passwordInput.type === "password";
        passwordInput.type = isVisible ? "text" : "password";
        button.setAttribute("aria-pressed", String(isVisible));
        button.setAttribute("aria-label", isVisible ? "Hide password" : "Show password");
    });
});
