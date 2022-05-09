<?php

namespace CLead;

use Carbon_Fields\Field;

class SpecialSession {

	private $namePrefix = 'special_session_';
	public static $namePrefixPublic = 'special_session_';

	/**
	 * @var null|\WP_User
	 */
	private $user = null;

	public function __construct() {}

	public function register_custom_fields() {

		$fields = [
			[
				'type' => 'complex',
				'name' => 'event_setup',
				'label' => 'Event Setup',
				'labels' => [
					'singular_name' => 'Event Setup',
					'plural_name' => 'Events Setup',
				],
				'min' => 1,
				'layout' => 'tabbed-vertical',
				'headerTemplate' => ' <%- [PREFIX]name ? [PREFIX]name : ($_index+1) %>',
				'fields' => [
					[
						'type' => 'date',
						'name' => 'publish_date',
						'label' => 'Publish Date',
					],
					[
						'type' => 'text',
						'name' => 'name',
						'label' => 'Name'
					],
					[
						'type' => 'image',
						'name' => 'logo',
						'label' => 'Logo',
						'valueType' => 'url',
					],
					[
						'type' => 'image',
						'name' => 'background',
						'label' => 'Background Image',
						'valueType' => 'url',
					],
					[
						'type' => 'date',
						'name' => 'date',
						'label' => 'Date'
					],
					[
						'type' => 'select',
						'name' => 'presentation_type',
						'label' => 'Event Presentation Type',
						'options' => [
							'physical' => 'Physical',
							'virtual' => 'Virtual',
						],
					],
					[
						'type' => 'text',
						'name' => 'location',
						'label' => 'Location'
					],
					[
						'type' => 'select',
						'name' => 'type',
						'label' => 'Event Type',
						'options' => [
							'public' => 'Public',
							'private' => 'Private',
						],
					],
					[
						'type' => 'rich_text',
						'name' => 'event_description',
						'label' => 'Event Description'
					],
					[
						'type' => 'complex',
						'name' => 'event_sessions',
						'label' => 'Event Sessions',
						'labels' => [
							'singular_name' => 'Event Session',
							'plural_name' => 'Event Sessions',
						],
						'min' => 1,
						'headerTemplate' => ' <%- [PREFIX]event_session_name ? [PREFIX]event_session_name : ($_index+1) %>',
						'fields' => [
							[
								'type' => 'text',
								'name' => 'event_session_name',
								'label' => 'Event Session: Name'
							],
							[
								'type' => 'date',
								'name' => 'event_session_date',
								'label' => 'Event Session: Date'
							],
							[
								'type' => 'time',
								'name' => 'event_session_from_time',
								'label' => 'Event Session: From Time'
							],
							[
								'type' => 'time',
								'name' => 'event_session_to_time',
								'label' => 'Event Session: To Time'
							],
							[
								'type' => 'text',
								'name' => 'event_session_chair',
								'label' => 'Event Session: Chair'
							],
							[
								'type' => 'complex',
								'name' => 'event_sub_sessions',
								'label' => 'Event Sub Sessions',
								'labels' => [
									'singular_name' => 'Event Sub Session',
									'plural_name' => 'Event Sub Sessions',
								],
								'min' => 1,
								'headerTemplate' => ' <%- [PREFIX]event_sub_session_name ? [PREFIX]event_sub_session_name : ($_index+1) %>',
								'fields' => [
									[
										'type' => 'text',
										'name' => 'event_sub_session_name',
										'label' => 'Event Sub Session: Name'
									],
									[
										'type' => 'complex',
										'name' => 'event_sub_session_speakers',
										'label' => 'Event Sub Session: Speakers',
										'labels' => [
											'singular_name' => 'Event Sub Session: Speaker',
											'plural_name' => 'Event Sub Session: Speakers',
										],
										'min' => 1,
										'headerTemplate' => ' <%- [PREFIX]event_sub_session_speaker_name ? [PREFIX]event_sub_session_speaker_name : ($_index+1) %>',
										'fields' => [
											[
												'type' => 'text',
												'name' => 'event_sub_session_speaker_name',
												'label' => 'Event Sub Session: Speaker Name',
											],
											[
												'type' => 'text',
												'name' => 'event_sub_session_speaker_affiliation',
												'label' => 'Event Sub Session: Speaker Affiliation',
											],
										],
									],
									[
										'type' => 'text',
										'name' => 'event_sub_session_duration',
										'label' => 'Event Sub Session: Duration',
										'attributes' => [
											'type' => 'number',
											'min' => 0,
										],
									],
									[
										'type' => 'rich_text',
										'name' => 'event_sub_session_description',
										'label' => 'Event Sub Session: Description'
									],
								],
							],
						],
					],
					[
						'type' => 'select',
						'name' => 'registration_type',
						'label' => 'Registration Type',
						'options' => [
							'internal' => 'Internal (hosted by https://iarai.ac.at)',
							'external' => 'External (hosted by 3rd party)',
						],
					],
					[
						'type' => 'text',
						'name' => 'registration_type_internal_event_id',
						'label' => 'RT: Internal - Event ID',
						'condition' => [
							'relation' => 'AND',
							[
								'field' => '[PREFIX]registration_type',
								'value' => 'internal',
								'compare' => '=',
							]
						],
					],
					[
						'type' => 'text',
						'name' => 'registration_type_external_url',
						'label' => 'RT: External - URL',
						'condition' => [
							'relation' => 'AND',
							[
								'field' => '[PREFIX]registration_type',
								'value' => 'external',
								'compare' => '=',
							]
						],
					],
				],
			],
		];

		$fieldsInContainer = $this->prepareFields($fields);

		return $fieldsInContainer;
	}

	public function prepareFields($fields) {
		$container = [];

		if (empty($fields)) { return $container; }

		foreach ($fields as $fieldSetup) {
			if (empty($fieldSetup)) { continue; }

			$field = Field::make(
				$fieldSetup['type'],
				$this->namePrefix . $fieldSetup['name'],
				_($fieldSetup['label'])
			);

			if (!empty($fieldSetup['condition'])) {
				$field->set_conditional_logic(
					$this->prepareCondition($fieldSetup['condition'])
				);
			}

			if (!empty($fieldSetup['attributes'])) {

				foreach ($fieldSetup['attributes'] as $attributeKey => $attributeValue) {
					$field->set_attribute($attributeKey, $attributeValue);
				}
			}

			if (
				$fieldSetup['type'] === 'image' &&
				!empty($fieldSetup['valueType'])
			) {
				$field->set_value_type($fieldSetup['valueType']);
			} elseif (
				$fieldSetup['type'] === 'select' &&
				!empty($fieldSetup['options'])
			) {
				$field->add_options($fieldSetup['options']);
			} elseif (
				$fieldSetup['type'] === 'complex'
			) {
				$layout = (
					!empty($fieldSetup['layout']) ?
					$fieldSetup['layout'] :
					'tabbed-horizontal'
				);

				$field
					->set_layout($layout)
					->set_min($fieldSetup['min']);

				if (!empty($fieldSetup['labels'])) {
					foreach ($fieldSetup['labels'] as $labelKey => $labelValue) {
						$fieldSetup['labels'][$labelKey] = _($labelValue);
					}

					$field->setup_labels($fieldSetup['labels']);
				}
				
				if (!empty($fieldSetup['fields'])) {
					$field->add_fields($this->prepareFields($fieldSetup['fields']));
				}

				if (!empty($fieldSetup['headerTemplate'])) {
					$field->set_header_template(
						$this->prepareTemplate($fieldSetup['headerTemplate'])
					);
				}
			}

			$container[] = $field;
		}

		return $container;
	}

	function prepareTemplate($template) {
		$tags = [
			'prefix' => $this->namePrefix,
		];

		foreach ($tags as $tagName => $tagValue) {
			$tagName = strtoupper('['. $tagName .']');
			$template = str_replace($tagName, $tagValue, $template);
		}

		return $template;
	}

	function prepareCondition($condition) {
		if (empty($condition)) { return $condition; }

		foreach ($condition as $key => $value) {
			
			if (!is_array($value)) {
				$condition[$key] = $this->prepareTemplate($value);
			} else {
				$condition[$key] = $this->prepareCondition($value);
			}
		}

		return $condition;
	}

	public static function getNamePrefix() {
		return self::$namePrefixPublic;
	}
}
