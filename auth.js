// drop-in replacement
function safeFetch(...args) {
  return fetch(...args)
    .then(response => {
      if (!response.ok) {
        const err = new Error("HTTP " + response.status);
        err.response = response;
        throw err;
      }
      return response;
    })
    .catch(err => {
      try { console.error("Network error:", err); } catch (e) {}
      try { if (typeof alert !== 'undefined') alert("Network error. Please try again."); } catch (e) {}
      throw err;z
    });
}

// ----- SIGN UP PASSWORD TOGGLE -----
const signuppasswordInput = document.getElementById('signup-password');
const togglesignupPassword = document.getElementById('toggle-signup-password');
if (signuppasswordInput && togglesignupPassword) {
  togglesignupPassword.addEventListener('click', () => {
    const type = signuppasswordInput.type === 'password' ? 'text' : 'password';
    signuppasswordInput.type = type;
    togglesignupPassword.textContent = type === 'password' ? '👁️' : '🙈';
  });
}

// ----- SIGN UP -----
const signupForm = document.getElementById('signup-form');
if (signupForm) {
  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const name = document.getElementById('signup-name')?.value.trim();
    const email = document.getElementById('signup-email')?.value.trim();
    const password = signuppasswordInput?.value;
    const phone = document.getElementById('signup-phone')?.value.trim();

     console.log({ name, email, password, phone }); // <-- Add this line for console debug

    if (!name || !email || !password || !phone) {
      alert('Please fill in all fields.');
      return;
    }
    try { 
      const res = await safeFetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password, phone, login_type: 'manual' }),
      });

      const data = await res.json();
      alert(data.message);
      if (data.success) {
        window.location.href = 'login.html';
      } 
     } catch (err) {
      alert('Error during sign-up.');
    }
 });
};

// ----- login PASSWORD TOGGLE -----
const loginpasswordInput = document.getElementById('login-password');
const toggleloginPassword = document.getElementById('toggle-login-password');
if (loginpasswordInput && toggleloginPassword) {
  toggleloginPassword.addEventListener('click', () => {
    const type = loginpasswordInput.type === 'password' ? 'text' : 'password';
    loginpasswordInput.type = type;
    toggleloginPassword.textContent = type === 'password' ? '👁️' : '🙈';
  });
}
// ----- LOGIN -----
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('login-email')?.value.trim();
    const password = loginpasswordInput?.value;

    if (!email || !password) {
      alert('Please fill in all fields.');
      return;
    }

    try {
      const res = await safeFetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();
      alert(data.message);
      if (data.success) {
        localStorage.setItem('app_logged_in', '1');
        localStorage.setItem('app_user', JSON.stringify(data.user));
         if (window.top) {
           window.top.location.href = 'index.php';
         } else {
           window.location.href = 'index.php';
         }
       }
    } 
    catch (err) {
      alert('Error during login.');
    }
  });
}

// ----- FORGOT PASSWORD / SEND OTP -----
const otpRequestForm = document.getElementById('otp-request-form');
if (otpRequestForm) {
  otpRequestForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('reset-email').value.trim();

    if (!email) {
      alert('Please enter your email address.');
      return;
    }

    try {
      const res = await safeFetch('send_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email }),
        credentials: 'include',
      });

      const data = await res.json();
      alert(data.message);
      if (data.success) {
        const reqForm = document.getElementById('otp-request-form');
        if (reqForm) reqForm.style.display = 'none';
        const resetFormEl = document.getElementById('reset-form');
        if (resetFormEl) {
          resetFormEl.style.display = 'block';
          resetFormEl.dataset.email = email;
        }
      }
    } catch (err) {
      alert('Error sending OTP. Please try again.');
    }
  });
}

// ----- RESET FLOW ON SAME PAGE -----
const resetFormEl = document.getElementById('reset-form');
if (resetFormEl) {
  resetFormEl.addEventListener('submit', async (e) => {
    e.preventDefault();

   const email = resetFormEl.dataset.email || document.getElementById('reset-email')?.value.trim();
    const otp = document.getElementById('otp')?.value.trim();
    const newPassword = document.getElementById('new-password')?.value.trim();

    if (!email || !otp || !newPassword) {
      alert('Please fill in all fields.');
      return;
    }

    try {
       let res = await safeFetch('verify_otp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, otp }),
        credentials: 'include',
      });

      let data = await res.json();
      if (!data.success) {
        alert(data.message || 'Invalid OTP.');
        return;
      }

      res = await safeFetch('reset_password.php', {        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({ email, password: newPassword }),
         credentials: 'include',
      });

      data = await res.json();
      alert(data.message);
      if (data.success) {
        window.location.href = 'login.html';
      }
    } catch (err) {
      alert('Error resetting password. Please try again.');
    }
  });
}