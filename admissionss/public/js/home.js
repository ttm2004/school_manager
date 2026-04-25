const registerForm = document.getElementById("registerForm");
const formMessage = document.getElementById("formMessage");

if (registerForm) {
    registerForm.addEventListener("submit", async function (e) {
        e.preventDefault();

        const formData = new FormData(registerForm);

        try {
            const response = await fetch("/api/admissions/register", {
                method: "POST",
                body: formData,
                headers: {
                    "Accept": "application/json"
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                let message = data.message || "Có lỗi xảy ra khi gửi hồ sơ.";

                if (data.errors) {
                    message = Object.values(data.errors)
                        .flat()
                        .join("<br>");
                }

                formMessage.innerHTML = `<div class="alert alert-danger">${message}</div>`;
                return;
            }

            formMessage.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            registerForm.reset();

        } catch (error) {
            console.error(error);
            formMessage.innerHTML = `<div class="alert alert-danger">Không thể kết nối server.</div>`;
        }
    });
}