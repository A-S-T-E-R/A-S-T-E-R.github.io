document.addEventListener('DOMContentLoaded', function() {
    function loadSection(sectionId) {
        const sectionElement = document.getElementById(sectionId);
        if (!sectionElement) {
            console.error(`Section element with id '${sectionId}' not found`);
            return;
        }
        
        fetch(`sections/${sectionId}.html`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                sectionElement.innerHTML = html;
                console.log(`Loaded section: ${sectionId}`);
            })
            .catch(error => {
                console.error(`Error loading ${sectionId}:`, error);
                sectionElement.innerHTML = `
                    <div class="text-center p-8">
                        <p class="text-green-400 text-xl">Error loading ${sectionId} content</p>
                        <p class="text-green-300 mt-2">Please check the console for details</p>
                    </div>
                `;
            });
    }
    
    const sections = ['home', 'about', 'projects', 'contact', 'resume'];
    sections.forEach(section => {
        loadSection(section);
    });
    
    window.addEventListener('scroll', function() {
        const scrollPosition = window.scrollY;
        
        const sectionElements = sections.map(id => document.getElementById(id));
        
        let currentSection = '';
        sectionElements.forEach(section => {
            if (!section) return;
            
            const sectionTop = section.offsetTop - 100;
            const sectionBottom = sectionTop + section.offsetHeight;
            
            if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                currentSection = section.id;
            }
        });
        
        if (currentSection) {
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === `#${currentSection}`) {
                    item.classList.add('active');
                }
            });
        }
    });
});
