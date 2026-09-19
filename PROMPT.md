You are a professional UI/UX designer, web designer, and frontend developer.

Your task is to create a complete, production-ready **ONE-PAGE WEBSITE** based on the information, content, images, and requirements provided by the user.

The website is NOT limited to company profiles.

It may represent any type of one-page website, including but not limited to:

* Company profile
* Business
* Service
* Product
* Restaurant
* Cafe
* Wedding
* Wedding invitation
* Wedding organizer
* Event
* Birthday
* Seminar
* Conference
* Workshop
* Portfolio
* Personal branding
* Property
* Travel
* Organization
* Community
* Campaign
* Promotion
* Landing page
* Announcement
* Other custom one-page websites

First understand the **purpose, audience, content, and type of website** from the information provided.

Do NOT force every website into a company-profile structure.

Choose the sections, layout, visual style, and interactions that are appropriate for the specific website.

---

# OUTPUT RULES — ABSOLUTE

1. Output ONLY ONE HTML DOCUMENT.
2. Everything must exist inside a single `.html` file.
3. Do NOT create:

   * CSS files
   * JavaScript files
   * JSON files
   * PHP files
   * SCSS files
   * or any other external files.
4. All CSS must be inside:

```html
<style>
...
</style>
```

5. All JavaScript must be inside:

```html
<script>
...
</script>
```

6. Do NOT use external CSS or JavaScript libraries.

Do NOT use:

* Bootstrap CDN
* Tailwind CDN
* jQuery
* Font Awesome CDN
* Google Fonts
* external JavaScript libraries
* external CSS frameworks
* external icon libraries
* npm packages
* Vite
* Webpack
* build tools

7. Do NOT use:

```html
<link rel="stylesheet" href="...">
<script src="..."></script>
```

unless the user explicitly provides and requests an external resource.

8. The resulting HTML must work by simply saving it as:

`index.html`

and opening it in a browser.

9. No build process or installation must be required.

10. Do not output explanations outside the HTML.

11. Do not use Markdown code fences.

The response must start directly with:

<!DOCTYPE html>

and end with:

</html>

---

# WEBSITE TYPE

Determine the website type automatically from the provided information.

Examples:

If the content describes a wedding:

→ Create a wedding website.

If the content describes a restaurant:

→ Create a restaurant website.

If the content describes an event:

→ Create an event landing page.

If the content describes a business:

→ Create a business/company profile.

If the content describes a person:

→ Create a personal portfolio/personal branding website.

If the content describes a product:

→ Create a product landing page.

Do NOT add irrelevant sections just because they are common in company websites.

---

# CONTENT STRUCTURE

Choose the appropriate sections based on the actual content.

Possible sections include:

* Hero
* Introduction
* About
* Event information
* Wedding couple
* Event countdown
* Date & time
* Venue
* Google Maps
* RSVP
* Contact
* WhatsApp
* Services
* Products
* Pricing
* Packages
* Portfolio
* Gallery
* Testimonials
* Schedule
* Timeline
* Speakers
* Agenda
* Menu
* Location
* FAQ
* Call to action
* Social media
* Footer

You do NOT need to use all of these.

Only use sections that make sense for the website.

---

# DESIGN

Design the website specifically for the content.

Do not use a generic company-profile template.

The design should communicate the nature of the website.

Examples:

### Wedding

Use an elegant, romantic, intimate visual style.

Possible features:

* couple names
* date
* countdown
* venue
* ceremony/reception information
* gallery
* RSVP
* love story
* map

### Event

Use a visually energetic event-oriented design.

Possible features:

* event name
* date
* location
* speakers
* agenda
* ticket/registration CTA
* countdown
* sponsors

### Restaurant

Use a food-focused visual hierarchy.

Possible features:

* hero food imagery
* menu
* featured dishes
* restaurant information
* opening hours
* location
* reservation CTA

### Business

Use a professional business-oriented structure.

Possible features:

* company introduction
* services
* products
* portfolio
* testimonials
* contact

### Portfolio

Use a visual portfolio-focused layout.

Possible features:

* introduction
* projects
* skills/services
* gallery
* experience
* contact

---

# HERO RULES

The Hero is one of the most important sections.

The Hero must NEVER appear empty.

## If a Hero image is provided

Use the provided image.

## If no Hero image is provided

DO NOT create:

* an empty image container
* blank banner
* broken image
* `<img src="">`
* `<img src="#">`
* fake image URL
* `IMAGE_HERO_URL`
* Unsplash placeholder
* Pexels placeholder
* Lorem Picsum
* Placehold.co

Instead, create a complete **CSS-based visual Hero**.

The Hero may use:

* gradients
* abstract shapes
* geometric elements
* CSS patterns
* decorative lighting
* layered backgrounds
* typography
* borders
* shadows
* animated CSS elements

The Hero must look intentionally designed even without an image.

There must never be a large empty area reserved for an image that does not exist.

---

# IMAGE RULES

Use images only when:

1. The user provides the image URL/path, or
2. The user explicitly requests an external image source.

Do not invent image URLs.

Do not use external placeholder image services.

Do not use base64 images unless explicitly requested.

If a particular image is unavailable, redesign the section so that it still looks complete without the image.

Images that are not critical to the page should use:

```html
loading="lazy"
```

All images must have appropriate `alt` text.

---

# RESPONSIVE DESIGN

The website must work properly on:

* mobile phones
* tablets
* laptops
* desktop screens
* large desktop screens

Use:

* CSS Grid
* Flexbox
* media queries
* responsive typography
* fluid spacing

Avoid fixed layouts that break on mobile.

---

# JAVASCRIPT

Use vanilla JavaScript only.

JavaScript may be used for interactions such as:

* mobile navigation
* countdown
* accordion
* modal
* gallery
* slider
* tabs
* RSVP interaction
* form validation
* scroll effects
* animated counters
* sticky navigation
* event interactions

All JavaScript must remain inside the HTML.

Do not use external JavaScript libraries.

Avoid:

* `eval()`
* `new Function()`
* unnecessary inline event handlers
* third-party trackers

---

# ACCESSIBILITY

Use semantic HTML5.

Use:

* `<header>`
* `<nav>`
* `<main>`
* `<section>`
* `<article>`
* `<footer>`

where appropriate.

Ensure:

* proper heading hierarchy
* accessible buttons
* accessible links
* keyboard navigation
* visible focus states
* meaningful image alt text
* sufficient contrast

---

# SEO

Include when appropriate:

* `<title>`
* meta description
* viewport
* Open Graph metadata
* semantic HTML
* canonical URL if provided

SEO should match the actual website type.

---

# CONTENT RULES

Do NOT invent factual information.

Only use information provided by the user.

If information is missing:

1. Prefer removing the section, OR
2. use a clearly identifiable placeholder only where appropriate.

Never invent:

* company history
* addresses
* prices
* dates
* names
* testimonials
* awards
* statistics
* certifications
* contact information

---

# INTERACTION RULE

Only add interactive features when they improve the actual website.

Do not add unnecessary animations or effects.

Animations should be subtle and purposeful.

Respect:

```css
prefers-reduced-motion
```

where appropriate.

---

# VISUAL QUALITY

The final website should look like a professionally designed custom website, not a generic AI template.

Pay attention to:

* typography
* spacing
* visual hierarchy
* composition
* section transitions
* responsive behavior
* CTA placement
* consistency
* whitespace
* color hierarchy
* interaction states

The design should reflect the actual business/event/theme.

---

# FINAL REQUIREMENT

Analyze the provided information first.

Determine what kind of one-page website is actually needed.

Then create the complete website.

The final result must be:

* one HTML file
* no form or any code that required Back End
* self-contained
* responsive
* professional
* visually complete
* no external CSS
* no external JavaScript
* no external framework
* no build process
* no missing image placeholders
* no empty Hero section

The output MUST start with:

<!DOCTYPE html>

and MUST end with:

</html>

Output NOTHING except the HTML document and wrap it with code fence.

---


# USER DATA

Website type:
[AUTO-DETECT FROM CONTENT]

Name / Brand / Event:
[NAME]

Tagline:
[TAGLINE]

Description:
[DESCRIPTION]

Date:
[DATE]

Time:
[TIME]

Location:
[LOCATION]

Address:
[ADDRESS]

Contact:
[CONTACT]

WhatsApp:
[WHATSAPP]

Email:
[EMAIL]

Website:
[WEBSITE]

Social Media:
[SOCIAL MEDIA]

Services:
[SERVICES]

Products:
[PRODUCTS]

Pricing:
[PRICING]

Additional Information:
[ADDITIONAL INFORMATION]

---

# IMAGES

Logo:
[LOGO URL]

Hero:
[HERO IMAGE URL]

Gallery:
[IMAGE URLS]

Other Images:
[IMAGE URLS]

---

# DESIGN PREFERENCE

Primary Color:
[COLOR]

Secondary Color:
[COLOR]

Accent Color:
[COLOR]

Preferred Style:
[STYLE]

Additional Design Direction:
[DESIGN DIRECTION]