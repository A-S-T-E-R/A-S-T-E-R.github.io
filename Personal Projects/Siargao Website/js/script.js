document.addEventListener("DOMContentLoaded", () => {
  const sections = ["home", "about", "attractions", "gallery", "contact"];
  const contentDiv = document.getElementById("content");

  // Load sections dynamically
  sections.forEach(section => {
    fetch(`sections/${section}.html`)
      .then(res => res.text())
      .then(data => {
        const wrapper = document.createElement("section");
        wrapper.id = section;
        wrapper.innerHTML = data;
        contentDiv.appendChild(wrapper);

        // Initialize gallery lightbox after gallery loads
        if(section === "gallery") initLightbox();
        // Initialize contact form after contact loads
        if(section === "contact") initContactForm();
      });
  });

  // Smooth scrolling
  document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      document.querySelector(link.getAttribute('href')).scrollIntoView({
        behavior: 'smooth'
      });
    });
  });
});

// Lightbox functionality
function initLightbox() {
  const galleryImages = document.querySelectorAll('.gallery img');
  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightbox-img');
  const closeBtn = document.querySelector('.lightbox .close');

  galleryImages.forEach(img => {
    img.addEventListener('click', () => {
      lightbox.style.display = 'flex';
      lightboxImg.src = img.src;
    });
  });

  closeBtn.addEventListener('click', () => {
    lightbox.style.display = 'none';
  });
}

// Contact form validation
function initContactForm() {
  document.getElementById('contactForm').addEventListener('submit', e => {
    e.preventDefault();
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const message = document.getElementById('message').value.trim();
    const formMessage = document.getElementById('formMessage');

    if(name && email && message) {
      formMessage.textContent = "Thank you for reaching out! We'll get back to you soon.";
      formMessage.style.color = "green";
      e.target.reset();
    } else {
      formMessage.textContent = "Please fill out all fields.";
      formMessage.style.color = "red";
    }
  });
}
