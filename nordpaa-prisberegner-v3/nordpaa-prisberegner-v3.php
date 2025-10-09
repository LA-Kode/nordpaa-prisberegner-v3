<?php
/**
 * Plugin Name: Prisberegner (Nordpaa)
 * Description: Modulbaseret prisberegner med admin-UI, PDF og lead dashboard. Shortcode: [nordpaa_prisberegner].
 * Version: 3.2.3
 * Author: Nordpaa
 * License: GPLv2 or later
 * Text Domain: nordpaa-prisberegner
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NP_PB_V3_VER', '3.2.3' );
define( 'NP_PB_V3_PATH', plugin_dir_path( __FILE__ ) );
define( 'NP_PB_V3_URL',  plugin_dir_url( __FILE__ ) );

/** Demo/default config – bruges både første gang og ved “Nulstil til demo” */
function np_pb_v3_demo_config() {
    return array(
        'color' => '#0B2650',
        'yearly_discount' => 10,
        'bands' => array(
            array('label'=>'1-25','min'=>1,'max'=>25),
            array('label'=>'26-50','min'=>26,'max'=>50),
            array('label'=>'51-100','min'=>51,'max'=>100),
            array('label'=>'101-200','min'=>101,'max'=>200),
            array('label'=>'201-350','min'=>201,'max'=>350),
            array('label'=>'351-500','min'=>351,'max'=>500),
            array('label'=>'+500','min'=>501,'max'=>999999),
        ),
        'modules' => array(
            array('name'=>'Awareness træning','desc'=>'IT-sikkerhed og phishing beskyttelse','prices'=>array(600,1260,1638,2129,2422,3460,4200)),
            array('name'=>'Phishing simulation','desc'=>'Test medarbejdernes opmærksomhed','prices'=>array(450,945,1229,1597,1817,2595,3150)),
            array('name'=>'Compliance pakke','desc'=>'GDPR og regeloverholdelse','prices'=>array(800,1560,2184,2800,3200,4600,5200)),
            array('name'=>'Advanced Security','desc'=>'Avanceret sikkerhedsovervågning','prices'=>array(1300,2100,3276,4200,5100,6800,7600)),
        ),
        'branding' => array(
            'company'=>'','logo'=>'',
            'primary'=>'#0B2650','secondary'=>'#6366f1','accent'=>'#ec4899',
            'font'=>'Inter','custom_css'=>'','powered'=>false,
        ),
    );
}

/** Læs konfiguration – falder altid tilbage til defaults */
function np_pb_v3_get_config() {
    $cfg = get_option('np_pb_v3_config');
    if ( ! $cfg || ! is_array($cfg) ) {
        $cfg = np_pb_v3_demo_config();
    }
    return $cfg;
}

/** Menus */
add_action('admin_menu', function () {
    add_menu_page(
        __('Prisberegner','nordpaa-prisberegner'),
        __('Prisberegner','nordpaa-prisberegner'),
        'manage_options',
        'np-pb-v3',
        'np_pb_v3_admin_page',
        'dashicons-calculator',
        35
    );
    add_submenu_page(
        'np-pb-v3',
        __('Lead Dashboard','nordpaa-prisberegner'),
        __('Leads','nordpaa-prisberegner'),
        'manage_options',
        'np-pb-v3-leads',
        'np_pb_v3_leads_page'
    );
});

/** Admin page */
function np_pb_v3_admin_page() {
    if ( ! current_user_can('manage_options') ) return;
    wp_enqueue_style('np-pb-v3-admin', NP_PB_V3_URL.'admin/css/admin.css', array(), NP_PB_V3_VER);
    wp_enqueue_script('np-pb-v3-admin', NP_PB_V3_URL.'admin/js/admin.js', array(), NP_PB_V3_VER, true);
    wp_localize_script('np-pb-v3-admin','npPBv3Admin', array(
        'nonce'  => wp_create_nonce('np_pb_v3_save'),
        'ajax'   => admin_url('admin-ajax.php'),
        'config' => np_pb_v3_get_config(), // <- sikrer “Generelt” aldrig er tom
    ));
    echo '<div class="np3-admin-wrap"><div id="np3-admin-app"></div></div><div class="np3-toast" style="display:none"></div>';
}

/** Leads page (med ekstra spacing) */
function np_pb_v3_leads_page() {
    if ( ! current_user_can('manage_options') ) return;
    $leads = (array) get_option('np_pb_v3_leads', array());
    wp_enqueue_style('np-pb-v3-admin', NP_PB_V3_URL.'admin/css/admin.css', array(), NP_PB_V3_VER);

    echo '<div class="np3-admin-wrap"><h1>Lead Dashboard</h1><div class="np3-card np3-leads-card">';
    echo '<table class="np3-table np3-table--spacious"><thead><tr>
            <th>Status</th><th>Navn</th><th>Email</th><th>Telefon</th><th>Firma</th><th>Pris</th><th>Type</th><th>Dato</th>
          </tr></thead><tbody>';
    if (empty($leads)) {
        echo '<tr><td colspan="8" class="np3-empty">Ingen leads endnu</td></tr>';
    } else {
        foreach (array_reverse($leads) as $L) {
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($L['status'] ?? 'Ny'),
                esc_html($L['name'] ?? ''),
                esc_html($L['email'] ?? ''),
                esc_html($L['phone'] ?? ''),
                esc_html($L['company'] ?? ''),
                esc_html($L['price'] ?? ''),
                esc_html($L['lead_type'] ?? ''),
                esc_html($L['date'] ?? '')
            );
        }
    }
    echo '</tbody></table></div></div>';
}

/** AJAX: Gem */
add_action('wp_ajax_np_pb_v3_save', function () {
    check_ajax_referer('np_pb_v3_save','nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error();

    $p = json_decode(stripslashes($_POST['config'] ?? ''), true);
    if ( ! $p ) wp_send_json_error();

    $cfg = np_pb_v3_get_config();
    $cfg['color'] = sanitize_hex_color($p['color'] ?? $cfg['color']);
    $cfg['yearly_discount'] = floatval($p['yearly_discount'] ?? 0);

    $cfg['bands'] = array();
    foreach ( (array) ($p['bands'] ?? array()) as $b ) {
        $cfg['bands'][] = array(
            'label'=>sanitize_text_field($b['label'] ?? ''),
            'min'  =>intval($b['min']   ?? 0),
            'max'  =>intval($b['max']   ?? 0),
        );
    }

    $cfg['modules'] = array();
    foreach ( (array) ($p['modules'] ?? array()) as $m ) {
        $cfg['modules'][] = array(
            'name'   => sanitize_text_field($m['name'] ?? ''),
            'desc'   => sanitize_textarea_field($m['desc'] ?? ''),
            'prices' => array_map('floatval', (array) ($m['prices'] ?? array())),
        );
    }

    if (isset($p['branding'])) {
        $b = $p['branding'];
        $cfg['branding'] = array(
            'company'    => sanitize_text_field($b['company'] ?? ''),
            'logo'       => esc_url_raw($b['logo'] ?? ''),
            'primary'    => sanitize_hex_color($b['primary'] ?? '#0B2650'),
            'secondary'  => sanitize_hex_color($b['secondary'] ?? '#6366f1'),
            'accent'     => sanitize_hex_color($b['accent'] ?? '#ec4899'),
            'font'       => sanitize_text_field($b['font'] ?? 'Inter'),
            'custom_css' => wp_kses_post($b['custom_css'] ?? ''),
            'powered'    => ! empty($b['powered']),
        );
    }

    update_option('np_pb_v3_config', $cfg);
    wp_send_json_success(array('config'=>$cfg));
});

/** AJAX: Nulstil til demo */
add_action('wp_ajax_np_pb_v3_reset', function () {
    check_ajax_referer('np_pb_v3_save','nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error();
    $cfg = np_pb_v3_demo_config();
    update_option('np_pb_v3_config', $cfg);
    wp_send_json_success(array('config'=>$cfg));
});

/** Shortcode – loader CSS/JS og sender config’en med */
add_shortcode('nordpaa_prisberegner', function () {
    $cfg = np_pb_v3_get_config();
    wp_enqueue_style ('np-pb-v3', NP_PB_V3_URL.'public/css/prisberegner.css', array(), NP_PB_V3_VER);
    wp_enqueue_script('np-pb-v3', NP_PB_V3_URL.'public/js/prisberegner.js', array(), NP_PB_V3_VER, true);
    wp_localize_script('np-pb-v3','npPBv3', array(
        'config'=>$cfg,
        'ajax'=>admin_url('admin-ajax.php'),
        'nonce'=>wp_create_nonce('np_pb_v3_front'),
    ));
    ob_start(); ?>
      <div class="np3-wrap" style="--np3-primary: <?php echo esc_attr($cfg['color']); ?>">
        <div id="np3-calc"></div>
      </div>
    <?php return ob_get_clean();
});
