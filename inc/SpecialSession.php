<?php

namespace CLead;

use Carbon_Fields\Field;

class SpecialSession {

	private $namePrefix = 'special_session_';

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
				'fields' => [
					[
						'type' => 'date',
						'name' => 'start_date',
						'label' => 'Start Date'
					],
					[
						'type' => 'date',
						'name' => 'end_date',
						'label' => 'End Date'
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
						'type' => 'text',
						'name' => 'location',
						'label' => 'Location'
					],
					[
						'type' => 'complex',
						'name' => 'event_time',
						'label' => 'Event Time',
						'labels' => [
							'singular_name' => 'Event Time',
							'plural_name' => 'Events Times',
						],
						'min' => 1,
						'fields' => [
							[
								'type' => 'time',
								'name' => 'event_time_single',
								'label' => 'Event Time: Single'
							],
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
						'label' => 'Event Session',
						'labels' => [
							'singular_name' => 'Event Session',
							'plural_name' => 'Events Sessions',
						],
						'min' => 1,
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
						],
					],
					[
						'type' => 'complex',
						'name' => 'event_sub_sessions',
						'label' => 'Event Sub Session',
						'labels' => [
							'singular_name' => 'Event Sub Session',
							'plural_name' => 'Events Sub Sessions',
						],
						'min' => 1,
						'fields' => [
							[
								'type' => 'text',
								'name' => 'event_sub_session_name',
								'label' => 'Event Session: Name'
							],
							[
								'type' => 'complex',
								'name' => 'event_sub_session_partners',
								'label' => 'Event Sub Session: Partner',
								'labels' => [
									'singular_name' => 'Event Sub Session: Partner',
									'plural_name' => 'Events Sub Sessions: Partners',
								],
								'min' => 1,
								'fields' => [
									[
										'type' => 'text',
										'name' => 'event_sub_session_partner_name',
										'label' => 'Event Sub Session: Partner Name',
									],
								],
							],
							[
								'type' => 'text',
								'name' => 'event_sub_session_duration',
								'label' => 'Event Sub Session: Duration'
							],
							[
								'type' => 'rich_text',
								'name' => 'event_sub_session_description',
								'label' => 'Event Sub Session: Description'
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
					],
					[
						'type' => 'text',
						'name' => 'registration_type_external_url',
						'label' => 'RT: External - URL',
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
			}

			$container[] = $field;
		}

		return $container;
	}
}
