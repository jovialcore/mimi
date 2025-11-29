function scrollToTop() {
  const duration = 2000;
  const start = window.scrollY;
  const startTime = performance.now()

  function animateScroll(currentTime) {
    
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    const ease = 1 - Math.pow(1 - progress, 3);

    window.scrollTo(0, start * (1 - ease));

    if (progress < 1) {
    
      requestAnimationFrame(animateScroll);
    }
  }

  requestAnimationFrame(animateScroll);
}
gsap.registerPlugin(ScrollTrigger);



gsap.to(".profile-pic", {
  y: -10,
  repeat: -1,
  yoyo: true,
  duration: 2,
  ease: "power1.inOut"
});

gsap.fromTo(".circle-bg",
  { scale: 0.95 },
  {
    scale: 1.05,
    duration: 3,
    repeat: -1,
    yoyo: true,
    ease: "power1.inOut"
  }
);

document.querySelectorAll(".folder").forEach(folder => {
  folder.addEventListener("mouseenter", () => {
    gsap.to(folder, { scale: 1.03, duration: 0.25, ease: "power2.out" });
  });
  folder.addEventListener("mouseleave", () => {
    gsap.to(folder, { scale: 1, duration: 0.25, ease: "power2.out" });
  });
});

gsap.utils.toArray(".write").forEach((el) => {
  gsap.from(el, {
    opacity: 0,
    y: 30,
    duration: 1,
    scrollTrigger: {
      trigger: el,
      start: "top 85%",
    }
  });
});

gsap.from(".sites a", {
  opacity: 0,
  y: 20,
  duration: 0.8,
  stagger: 0.1,
  scrollTrigger: {
    trigger: ".sites",
    start: "top 80%"
  }
});

const words = [
  "A UI/UX Mentor",
  "A Creative Thinker",
  "A Product Designer",
  "A Problem Solver"
];

let wordIndex = 0;
let charIndex = 0;
let current = "";
let letter = "";

function typeEffect() {
  if (wordIndex === words.length) wordIndex = 0;

  current = words[wordIndex];
  letter = current.slice(0, ++charIndex);

  document.querySelector(".typed").textContent = letter;

  if(letter.length === current.length) {
    wordIndex++;
    charIndex = 0;
    setTimeout(typeEffect, 1200);
  } else {
    setTimeout(typeEffect, 70);
  }
}

typeEffect();




// ===========================
// GSAP ANIMATIONS (UNIQUE)
// ===========================
gsap.registerPlugin(ScrollTrigger);

// ===== Smooth Fade Navbar Background =====
gsap.to("#header", {
  backgroundColor: "rgba(13,1,18,0.6)",
  backdropFilter: "blur(20px)",
  duration: 0.4,
  scrollTrigger: {
    trigger: "body",
    start: "40px top",
    toggleActions: "play none none reverse"
  }
});

// ===== Folder Card Slide Up Animation =====
gsap.from(".folder", {
  opacity: 0,
  y: 80,
  duration: 1.2,
  ease: "power3.out",
  scrollTrigger: {
    trigger: ".folder",
    start: "top 85%",
  }
});

// ===== Profile Thumbnail Floating =====
gsap.to(".profie-pic", {
  y: -8,
  repeat: -1,
  yoyo: true,
  duration: 2.2,
  ease: "power1.inOut"
});

// ===== Highlight Blocks Fade-In Stagger =====
gsap.utils.toArray(".kenya").forEach((el, i) => {
  gsap.from(el, {
    opacity: 0,
    x: -40,
    duration: 0.8,
    delay: i * 0.15,
    scrollTrigger: {
      trigger: el,
      start: "top 90%"
    }
  });
});

// ===== Africa Sections Smooth Reveal =====
gsap.utils.toArray(".africa").forEach((section) => {
  gsap.from(section, {
    opacity: 0,
    y: 50,
    duration: 1.1,
    ease: "power2.out",
    scrollTrigger: {
      trigger: section,
      start: "top 85%"
    }
  });
});

// ===== Color Swatch Wiggle =====
gsap.from(".seperate", {
  opacity: 0,
  rotate: -3,
  duration: 1.4,
  ease: "elastic.out(1, 0.4)",
  scrollTrigger: {
    trigger: ".seperate",
    start: "top 85%"
  }
});