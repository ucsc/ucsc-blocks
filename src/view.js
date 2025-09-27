/**
 * Frontend JavaScript for UCSC Events block
 */

document.addEventListener('DOMContentLoaded', function() {
	// Add click handlers and interactions for event items
	const eventItems = document.querySelectorAll('.wp-block-telex-ucsc-events .ucsc-event-item');
	
	eventItems.forEach(function(item) {
		// Add hover effects for better interactivity
		item.addEventListener('mouseenter', function() {
			this.style.transition = 'transform 0.2s ease';
			this.style.transform = 'translateY(-2px)';
		});
		
		item.addEventListener('mouseleave', function() {
			this.style.transform = 'translateY(0)';
		});

		// Make the entire item clickable if it contains a link
		const link = item.querySelector('a');
		if (link && !item.classList.contains('clickable-item')) {
			item.classList.add('clickable-item');
			item.style.cursor = 'pointer';
			
			item.addEventListener('click', function(e) {
				// Don't trigger if clicking directly on the link
				if (e.target.tagName.toLowerCase() === 'a') {
					return;
				}
				
				// Open link in new tab
				window.open(link.href, '_blank', 'noopener,noreferrer');
			});
		}
	});

	// Handle image loading errors gracefully
	const eventImages = document.querySelectorAll('.wp-block-telex-ucsc-events .ucsc-event-image img');
	eventImages.forEach(function(img) {
		img.addEventListener('error', function() {
			const imageContainer = this.closest('.ucsc-event-image');
			if (imageContainer) {
				imageContainer.style.display = 'none';
			}
		});
	});

	// Lazy load images if IntersectionObserver is supported
	if ('IntersectionObserver' in window) {
		const imageObserver = new IntersectionObserver((entries, observer) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					const img = entry.target;
					if (img.dataset.src) {
						img.src = img.dataset.src;
						img.removeAttribute('data-src');
						observer.unobserve(img);
						
						// Add fade in animation
						img.style.opacity = '0';
						img.style.transition = 'opacity 0.3s ease';
						
						img.onload = function() {
							this.style.opacity = '1';
						};
					}
				}
			});
		}, {
			rootMargin: '50px 0px',
			threshold: 0.1
		});

		// Observe images with data-src attribute for lazy loading
		const lazyImages = document.querySelectorAll('.wp-block-telex-ucsc-events img[data-src]');
		lazyImages.forEach(img => imageObserver.observe(img));
	}

	// Add smooth scroll behavior for anchor links
	const anchorLinks = document.querySelectorAll('.wp-block-telex-ucsc-events a[href*="#"]');
	anchorLinks.forEach(function(link) {
		link.addEventListener('click', function(e) {
			const href = this.getAttribute('href');
			const hashIndex = href.indexOf('#');
			
			if (hashIndex !== -1) {
				const hash = href.substring(hashIndex + 1);
				const target = document.getElementById(hash);
				
				if (target && href.indexOf(window.location.hostname) !== -1) {
					e.preventDefault();
					target.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			}
		});
	});

	// Debug logging if needed
	if (window.location.search.includes('debug=ucsc-events')) {
		console.log('UCSC Events block loaded');
		console.log('Found event blocks:', document.querySelectorAll('.wp-block-telex-ucsc-events').length);
		console.log('Nonce available:', !!window.ucscEventsNonce);
	}
});