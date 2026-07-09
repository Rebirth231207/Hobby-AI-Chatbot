<?php
/**
 * کلاس مدیریت تنظیمات افزونه پشتیبانی هوشمند.
 *
 * تنظیمات در سه دسته مجزا در جدول wp_options ذخیره می‌شوند: تنظیمات
 * ظاهری فرانت، تنظیمات اتصال هوش مصنوعی و تنظیمات منطق پاسخ‌دهی. هر
 * دسته به صورت یک آرایه سریالایز‌شده با update_option ذخیره می‌شود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Settings {

	/**
	 * آدرس بیس ثابت و غیرقابل تغییر API هوش مصنوعی طبق مستندات پروژه.
	 *
	 * این مقدار هاردکد است و حتی در صورت تلاش برای ارسال مقدار متفاوت
	 * از فرانت پنل ادمین، نادیده گرفته شده و همیشه همین مقدار ذخیره
	 * می‌شود.
	 *
	 * @var string
	 */
	const AI_BASE_URL = 'https://api.gapgpt.app/v1';

	/**
	 * دریافت تنظیمات ظاهری فرانت با مقادیر پیش‌فرض در صورت عدم وجود.
	 *
	 * @return array
	 */
	public static function get_ui_settings() {
		$defaults = array(
			'logo_url'         => '',
			'welcome_text'     => 'پشتیبانی فروشگاه هابی تِک همراه شماست؛ چطور می‌تونم کمکتون کنم؟',
			'tooltip_text'     => 'پشتیبانی آنلاین هابی تک',
			'bale_link'        => '',
			'bale_icon_url'    => '',
			'telegram_link'    => '',
			'telegram_icon_url' => '',
			'rubika_link'      => '',
			'rubika_icon_url'  => '',
		);

		$saved = get_option( 'ss_settings_ui', array() );

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * ذخیره تنظیمات ظاهری فرانت پس از sanitize کامل.
	 *
	 * @param array $data آرایه خام دریافتی از REST API.
	 * @return void
	 */
	public static function save_ui_settings( $data ) {
		$sanitized = array(
			'logo_url'          => esc_url_raw( isset( $data['logo_url'] ) ? $data['logo_url'] : '' ),
			'welcome_text'      => sanitize_textarea_field( isset( $data['welcome_text'] ) ? $data['welcome_text'] : '' ),
			'tooltip_text'      => sanitize_text_field( isset( $data['tooltip_text'] ) ? $data['tooltip_text'] : '' ),
			'bale_link'         => esc_url_raw( isset( $data['bale_link'] ) ? $data['bale_link'] : '' ),
			'bale_icon_url'     => esc_url_raw( isset( $data['bale_icon_url'] ) ? $data['bale_icon_url'] : '' ),
			'telegram_link'     => esc_url_raw( isset( $data['telegram_link'] ) ? $data['telegram_link'] : '' ),
			'telegram_icon_url' => esc_url_raw( isset( $data['telegram_icon_url'] ) ? $data['telegram_icon_url'] : '' ),
			'rubika_link'       => esc_url_raw( isset( $data['rubika_link'] ) ? $data['rubika_link'] : '' ),
			'rubika_icon_url'   => esc_url_raw( isset( $data['rubika_icon_url'] ) ? $data['rubika_icon_url'] : '' ),
		);

		update_option( 'ss_settings_ui', $sanitized );
	}

	/**
	 * دریافت تنظیمات اتصال هوش مصنوعی. آدرس بیس همیشه از ثابت کلاس
	 * خوانده می‌شود، نه از دیتابیس، تا تحت هیچ شرایطی قابل تغییر نباشد.
	 *
	 * @return array
	 */
	public static function get_ai_settings() {
		$defaults = array(
			'api_key' => '',
			'model'   => '',
		);

		$saved = get_option( 'ss_settings_ai', array() );
		$saved = wp_parse_args( $saved, $defaults );

		$saved['base_url'] = self::AI_BASE_URL;

		return $saved;
	}

	/**
	 * ذخیره تنظیمات هوش مصنوعی. مقدار base_url ارسالی از فرانت به طور
	 * کامل نادیده گرفته می‌شود و هرگز در دیتابیس ذخیره نمی‌شود.
	 *
	 * @param array $data آرایه خام دریافتی از REST API.
	 * @return void
	 */
	public static function save_ai_settings( $data ) {
		$sanitized = array(
			'api_key' => sanitize_text_field( isset( $data['api_key'] ) ? $data['api_key'] : '' ),
			'model'   => sanitize_text_field( isset( $data['model'] ) ? $data['model'] : '' ),
		);

		update_option( 'ss_settings_ai', $sanitized );
	}

	/**
	 * دریافت تنظیمات منطق پاسخ‌دهی و رفتار سیستم.
	 *
	 * @return array
	 */
	public static function get_logic_settings() {
		$defaults = array(
			'response_mode'     => 'human', // human | ai | hybrid
			'negative_prompt'   => '',
		);

		$saved = get_option( 'ss_settings_logic', array() );

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * ذخیره تنظیمات منطق پاسخ‌دهی.
	 *
	 * @param array $data آرایه خام دریافتی از REST API.
	 * @return void
	 */
	public static function save_logic_settings( $data ) {
		$allowed_modes = array( 'human', 'ai', 'hybrid' );
		$mode          = isset( $data['response_mode'] ) ? $data['response_mode'] : 'human';

		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'human';
		}

		$sanitized = array(
			'response_mode'   => $mode,
			'negative_prompt' => sanitize_textarea_field( isset( $data['negative_prompt'] ) ? $data['negative_prompt'] : '' ),
		);

		update_option( 'ss_settings_logic', $sanitized );
	}

	/* =====================================================================
	 * مدیریت واحدهای مربوطه تیکت (Departments)
	 * ===================================================================== */

	/**
	 * دریافت لیست واحدهای تیکت با مقدار پیش‌فرض در صورت عدم تنظیم قبلی.
	 *
	 * هر واحد شامل یک شناسه عددی ثابت (id) و یک نام قابل ویرایش (name)
	 * است. شناسه هرگز تغییر نمی‌کند تا تیکت‌های ثبت‌شده قبلی، حتی پس از
	 * تغییر نام واحد توسط ادمین، همچنان به واحد درست اشاره کنند.
	 *
	 * @return array آرایه‌ای از واحدها به فرمت [ ['id' => 1, 'name' => 'پشتیبانی فنی'], ... ].
	 */
	public static function get_departments() {
		$defaults = array(
			array(
				'id'   => 1,
				'name' => 'پشتیبانی فنی',
			),
			array(
				'id'   => 2,
				'name' => 'واحد فروش',
			),
			array(
				'id'   => 3,
				'name' => 'امور مالی و حسابداری',
			),
			array(
				'id'   => 4,
				'name' => 'گارانتی و مرجوعی',
			),
		);

		$saved = get_option( 'ss_settings_departments', null );

		if ( null === $saved || ! is_array( $saved ) || empty( $saved ) ) {
			return $defaults;
		}

		return $saved;
	}

	/**
	 * دریافت نام نمایشی یک واحد بر اساس شناسه عددی آن.
	 *
	 * @param int $department_id شناسه عددی واحد.
	 * @return string نام واحد، یا رشته خالی در صورت عدم وجود.
	 */
	public static function get_department_name( $department_id ) {
		$departments = self::get_departments();

		foreach ( $departments as $dept ) {
			if ( (int) $dept['id'] === (int) $department_id ) {
				return $dept['name'];
			}
		}

		return '';
	}

	/**
	 * بررسی معتبر بودن یک شناسه واحد (جهت اعتبارسنجی هنگام ثبت تیکت).
	 *
	 * @param int $department_id شناسه عددی واحد.
	 * @return bool
	 */
	public static function is_valid_department( $department_id ) {
		$departments = self::get_departments();

		foreach ( $departments as $dept ) {
			if ( (int) $dept['id'] === (int) $department_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * ذخیره لیست کامل واحدهای تیکت پس از sanitize و اعتبارسنجی.
	 *
	 * شناسه‌های موجود حفظ می‌شوند و برای واحدهای تازه اضافه‌شده (بدون
	 * شناسه یا با شناسه نامعتبر)، یک شناسه جدید و یکتا تولید می‌شود.
	 *
	 * @param array $data آرایه خام واحدها دریافتی از REST API.
	 * @return array لیست نهایی ذخیره‌شده.
	 */
	public static function save_departments( $data ) {
		$current      = self::get_departments();
		$max_id       = 0;

		foreach ( $current as $dept ) {
			if ( (int) $dept['id'] > $max_id ) {
				$max_id = (int) $dept['id'];
			}
		}

		$sanitized = array();

		if ( is_array( $data ) ) {
			foreach ( $data as $dept ) {
				$name = isset( $dept['name'] ) ? sanitize_text_field( $dept['name'] ) : '';

				if ( '' === trim( $name ) ) {
					continue; // نادیده گرفتن ردیف‌های خالی
				}

				$id = isset( $dept['id'] ) ? absint( $dept['id'] ) : 0;

				if ( 0 === $id ) {
					$max_id++;
					$id = $max_id;
				}

				$sanitized[] = array(
					'id'   => $id,
					'name' => $name,
				);
			}
		}

		// حداقل یک واحد باید همیشه وجود داشته باشد تا فرم تیکت خالی نماند
		if ( empty( $sanitized ) ) {
			$sanitized = self::get_departments();
		}

		update_option( 'ss_settings_departments', $sanitized );

		return $sanitized;
	}
}
