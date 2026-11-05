<?php
/**
 * Editable content: Site Settings and the fields of every page template, registered with
 * ElRoi Site Editor. Defaults are placeholder copy for a local service business; replace them
 * with the client's copy when building the site (they are what a fresh page shows).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hero fields shared by inner pages.
 *
 * @param string $h1 Default heading.
 */
function es_hero_fields( $h1 = '' ) {
	return array(
		'type'   => 'group',
		'label'  => 'Top of the page',
		'fields' => array(
			'eyebrow' => array( 'label' => 'Small label above the heading', 'half' => true ),
			'h1'      => array( 'type' => 'heading', 'label' => 'Main heading (H1)', 'default' => $h1, 'help' => 'Leave empty to use the page title.' ),
			'lede'    => array( 'type' => 'textarea', 'label' => 'Introduction' ),
			'photo'   => array( 'type' => 'image', 'label' => 'Background photo' ),
		),
	);
}

/**
 * Content sections: heading, text, checklist, image and cards.
 */
function es_sections_field() {
	return array(
		'type'   => 'repeater',
		'label'  => 'Sections',
		'item'   => 'Section',
		'title'  => 'heading',
		'fields' => array(
			'heading'    => array( 'type' => 'heading', 'label' => 'Section heading' ),
			'eyebrow'    => array( 'label' => 'Small label above the heading (optional)' ),
			'paragraphs' => array( 'type' => 'paragraphs', 'label' => 'Text', 'rich' => true ),
			'bullets'    => array( 'type' => 'lines', 'label' => 'Checklist', 'help' => 'Shown with check marks.' ),
			'image'      => array( 'type' => 'image', 'label' => 'Image (optional)' ),
			'cards'      => array(
				'type'   => 'repeater',
				'label'  => 'Cards (optional)',
				'item'   => 'Card',
				'fields' => array(
					'heading' => array( 'label' => 'Card heading' ),
					'text'    => array( 'type' => 'textarea', 'label' => 'Text' ),
					'link'    => array( 'type' => 'link', 'label' => 'Link (optional)' ),
					'image'   => array( 'type' => 'image', 'label' => 'Image (optional)' ),
				),
			),
		),
	);
}

/**
 * Frequently asked questions.
 */
function es_faq_field() {
	return array(
		'type'   => 'repeater',
		'label'  => 'Frequently asked questions',
		'item'   => 'Question',
		'title'  => 'q',
		'help'   => 'Start each answer with the direct answer: Google can show it in search results.',
		'fields' => array(
			'q' => array( 'label' => 'Question' ),
			'a' => array( 'type' => 'textarea', 'label' => 'Answer', 'rows' => 4, 'rich' => true ),
		),
	);
}

/**
 * Call to action band at the end of a page (empty fields use the defaults).
 */
function es_cta_field() {
	return array(
		'type'   => 'group',
		'label'  => 'Call to action (bottom of the page)',
		'fields' => array(
			'heading' => array( 'type' => 'heading', 'label' => 'Heading', 'default' => 'Ready to start *your project?*' ),
			'text'    => array( 'type' => 'textarea', 'label' => 'Text', 'default' => 'Tell us what you have in mind. We reply within {response_hours} hours.' ),
			'button'  => array( 'label' => 'Button text', 'default' => 'Get a free estimate', 'half' => true, 'link_text' => true ),
		),
	);
}

add_action(
	'after_setup_theme',
	function () {
		if ( ! function_exists( 'elroi_register_template' ) ) {
			return;
		}

		// A client child theme changes defaults or adds fields through the es_settings filter.
		elroi_register_settings(
			apply_filters(
				'es_settings',
				array(
					'support'  => 'ElRoi Marketing (elroi.marketing)',
					'sections' => array(
						'business' => array(
							'label'  => 'Business',
							'fields' => array(
								'company'       => array( 'label' => 'Business name', 'default' => 'Your Company' ),
								'business_type' => array(
									'type'    => 'select',
									'label'   => 'Type of business (for Google)',
									'options' => array( 'LocalBusiness', 'GeneralContractor', 'HomeAndConstructionBusiness', 'ProfessionalService', 'MedicalBusiness', 'LegalService', 'Restaurant', 'Store' ),
									'default' => 'LocalBusiness',
									'half'    => true,
								),
								'description'   => array( 'type' => 'textarea', 'label' => 'One-sentence description', 'default' => 'Licensed and insured local experts.', 'help' => 'Used by Google and in the footer.' ),
								'service_areas' => array( 'type' => 'lines', 'label' => 'Towns and areas you serve', 'help' => 'One per line, e.g. Morristown, NJ. Google uses these to show the business for local searches, most of all when there is no storefront address.' ),
							),
						),
						'contact'  => array(
							'label'  => 'Contact',
							'fields' => array(
								'phone'  => array( 'label' => 'Phone', 'default' => '(555) 010-0000', 'half' => true ),
								'email'  => array( 'type' => 'email', 'label' => 'Email', 'default' => 'hello@example.com', 'half' => true ),
								'street' => array( 'label' => 'Street address', 'clearable' => true ),
								'city'   => array( 'label' => 'City', 'half' => true, 'clearable' => true ),
								'region' => array( 'label' => 'State', 'half' => true, 'clearable' => true ),
								'zip'    => array( 'label' => 'ZIP code', 'half' => true, 'clearable' => true ),
								'hours'  => array( 'type' => 'lines', 'label' => 'Opening hours', 'help' => 'One line each, e.g. Mon to Fri: 8am to 6pm. Leave empty to hide.' ),
							),
						),
						'numbers'  => array(
							'label'       => 'Numbers shown on the site',
							'description' => 'Type {years}, {reviews} or {response_hours} in any text to show these.',
							'fields'      => array(
								'years'          => array( 'label' => 'Years in business', 'default' => '10+', 'half' => true ),
								'reviews'        => array( 'label' => 'Number of reviews', 'default' => '50', 'half' => true ),
								'response_hours' => array( 'label' => 'Reply time (hours)', 'default' => '24', 'half' => true ),
							),
						),
						'form'     => array(
							'label'  => 'Contact form',
							'fields' => array(
								'form_services' => array( 'type' => 'lines', 'label' => 'Services in the form menu', 'default' => array( 'Service one', 'Service two', 'Something else' ) ),
								'form_email'    => array( 'type' => 'email', 'label' => 'Send form messages to', 'help' => 'Leave empty to use the website administrator email.' ),
							),
						),
						'social'   => array(
							'label'  => 'Social profiles',
							'fields' => array(
								'facebook'  => array( 'type' => 'url', 'label' => 'Facebook', 'clearable' => true, 'help' => 'Leave empty to hide the icon.' ),
								'instagram' => array( 'type' => 'url', 'label' => 'Instagram', 'clearable' => true ),
								'linkedin'  => array( 'type' => 'url', 'label' => 'LinkedIn', 'clearable' => true ),
								'google'    => array( 'type' => 'url', 'label' => 'Google Business profile', 'clearable' => true ),
							),
						),
					),
				)
			)
		);

		elroi_register_template(
			'front-page',
			array(
				'label'     => 'Home page',
				'box_title' => 'Home page content',
				'fields'    => array(
					'hero'     => array(
						'type'   => 'group',
						'label'  => 'Top of the page',
						'fields' => array(
							'eyebrow'    => array( 'label' => 'Small label above the heading', 'default' => 'Licensed & insured' ),
							'h1'         => array( 'type' => 'heading', 'label' => 'Main heading (H1)', 'default' => 'Local experts who *get it done right.*' ),
							'lede'       => array( 'type' => 'textarea', 'label' => 'Introduction', 'default' => '{years} years serving homeowners and businesses with honest pricing and clean work.' ),
							'photo'      => array( 'type' => 'image', 'label' => 'Background photo' ),
							'button'     => array( 'label' => 'Second button text', 'default' => 'Our services', 'half' => true, 'link_text' => true ),
							'button_url' => array( 'type' => 'link', 'label' => 'Second button link', 'default' => '#services', 'half' => true ),
							'form_title' => array( 'label' => 'Form box title', 'default' => 'Get a free estimate', 'half' => true ),
							'form_text'  => array( 'label' => 'Form box text', 'default' => 'We reply within {response_hours} hours.', 'half' => true ),
						),
					),
					'badges'   => array( 'type' => 'lines', 'label' => 'Trust badges under the top', 'default' => array( 'Licensed & insured', '{reviews} five-star reviews', 'Free estimates' ) ),
					'services' => array(
						'type'   => 'group',
						'label'  => 'Services',
						'fields' => array(
							'heading' => array( 'type' => 'heading', 'label' => 'Heading', 'default' => 'What we *do best.*' ),
							'intro'   => array( 'type' => 'textarea', 'label' => 'Text', 'default' => 'One team for every part of the job, from the first visit to the final walkthrough.' ),
							'items'   => array(
								'type'    => 'repeater',
								'label'   => 'Services',
								'item'    => 'Service',
								'title'   => 'name',
								'fields'  => array(
									'name'  => array( 'label' => 'Name' ),
									'text'  => array( 'type' => 'textarea', 'label' => 'Short description' ),
									'link'  => array( 'type' => 'link', 'label' => 'Link' ),
									'image' => array( 'type' => 'image', 'label' => 'Photo' ),
								),
								'default' => array(
									array( 'name' => 'Service one', 'text' => 'A short, concrete description of what is included.', 'link' => '/' ),
									array( 'name' => 'Service two', 'text' => 'A short, concrete description of what is included.', 'link' => '/' ),
									array( 'name' => 'Service three', 'text' => 'A short, concrete description of what is included.', 'link' => '/' ),
								),
							),
						),
					),
					'about'    => array(
						'type'   => 'group',
						'label'  => 'About',
						'fields' => array(
							'heading'    => array( 'type' => 'heading', 'label' => 'Heading', 'default' => 'Why neighbors *choose us.*' ),
							'paragraphs' => array( 'type' => 'paragraphs', 'label' => 'Text', 'rich' => true, 'default' => array( 'Tell the story of the business here: who runs it, how long it has served the area, and what customers can count on.' ) ),
							'photo'      => array( 'type' => 'image', 'label' => 'Photo' ),
							'facts'      => array(
								'type'    => 'repeater',
								'label'   => 'Key facts',
								'item'    => 'Fact',
								'title'   => 'value',
								'fields'  => array(
									'value' => array( 'label' => 'Big number or word', 'half' => true ),
									'label' => array( 'label' => 'What it means', 'half' => true ),
								),
								'default' => array(
									array( 'value' => '{years}', 'label' => 'Years in business' ),
									array( 'value' => '{reviews}', 'label' => 'Five-star reviews' ),
									array( 'value' => '{response_hours}h', 'label' => 'Typical reply time' ),
								),
							),
						),
					),
					'reviews'  => array(
						'type'   => 'group',
						'label'  => 'Reviews',
						'fields' => array(
							'heading' => array( 'type' => 'heading', 'label' => 'Heading', 'default' => 'What our *customers say.*' ),
							'items'   => array(
								'type'    => 'repeater',
								'label'   => 'Reviews',
								'item'    => 'Review',
								'title'   => 'name',
								'help'    => 'Copy real reviews word for word, with the customer\'s first name and last initial.',
								'fields'  => array(
									'name'   => array( 'label' => 'Name', 'half' => true ),
									'detail' => array( 'label' => 'Project or town', 'half' => true ),
									'quote'  => array( 'type' => 'textarea', 'label' => 'Review' ),
								),
								'default' => array(
									array( 'name' => 'Customer A.', 'detail' => 'Project type', 'quote' => 'Replace with a real review.' ),
									array( 'name' => 'Customer B.', 'detail' => 'Project type', 'quote' => 'Replace with a real review.' ),
								),
							),
						),
					),
					'faqs'     => es_faq_field(),
					'cta'      => es_cta_field(),
				),
			)
		);

		elroi_register_template(
			'templates/service.php',
			array(
				'label'  => 'Service page',
				'fields' => array(
					'hero'     => es_hero_fields(),
					'sections' => es_sections_field(),
					'video'    => es_video_field(),
					'faqs'     => es_faq_field(),
					'cta'      => es_cta_field(),
				),
			)
		);

		elroi_register_template(
			'templates/page.php',
			array(
				'label'  => 'General page',
				'fields' => array(
					'hero'     => es_hero_fields(),
					'sections' => es_sections_field(),
					'faqs'     => es_faq_field(),
				),
			)
		);

		elroi_register_template(
			'templates/contact.php',
			array(
				'label'  => 'Contact page',
				'fields' => array(
					'hero' => es_hero_fields(),
					'form' => array(
						'type'   => 'group',
						'label'  => 'Form',
						'fields' => array(
							'title' => array( 'label' => 'Form title', 'default' => 'Tell us about your project', 'half' => true ),
							'text'  => array( 'label' => 'Form text', 'default' => 'We reply within {response_hours} hours.', 'half' => true ),
						),
					),
					'faqs' => es_faq_field(),
				),
			)
		);
	}
);
