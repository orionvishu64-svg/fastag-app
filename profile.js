// redirect to index.php only those didn't login 
fetch('check_login.php', { credentials: 'include' })
    .then(res => res.text())
    .then(status => {
      if (status.trim() !== 'logged_in') {
        // Redirect back to homepage, where the login overlay will show
        window.location.href = 'index.php';
      }
    });

document.addEventListener("DOMContentLoaded", () => {
  fetch("profile.php")
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const u = data.user;
        document.getElementById("profile-name").value = u.name || "";
        document.getElementById("profile-email").value = u.email || "";
        document.getElementById("profile-phone").value = u.phone || "";
        document.getElementById("profile-house_no").value = u.house_no || "";
        document.getElementById("profile-landmark").value = u.landmark || "";
        document.getElementById("profile-city").value = u.city || "";
        document.getElementById("profile-pincode").value = u.pincode || "";
      } else {
        document.getElementById("status-message").innerText = data.message;
      }
    });

  document.getElementById("profile-form").addEventListener("submit", function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    fetch("update_profile.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        document.getElementById("status-message").innerText = data.message;
      });
  });
});
