<?php
/**
 * Plugin Name: Nordpaa Prisberegner v3
 * Description: Prisberegner med moderne UI, PDF-tilbud og lead-opsamling. Shortcode: [nordpaa_prisberegner].
 * Version:     3.2.0
 * Author:      Nordpaa
 * License:     GPLv2 or later
 * Text Domain: nordpaa-prisberegner
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NP_PB_V3_VER', '3.2.0' );
define( 'NP_PB_V3_PATH', plugin_dir_path( __FILE__ ) );
define( 'NP_PB_V3_URL', plugin_dir_url( __FILE__ ) );

function np_pb_v3_demo_config() {
    return array(
        'color' => '#0B2650','yearly_discount' => 10,
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
        'branding' => array('company'=>'','logo'=>'','primary'=>'#0B2650','secondary'=>'#6366f1','accent'=>'#ec4899','font'=>'Inter','custom_css'=>'','powered'=>false)
    );
}
function np_pb_v3_get_config(){ $cfg=get_option('np_pb_v3_config'); if(!$cfg||!is_array($cfg)) $cfg=np_pb_v3_demo_config(); return $cfg; }

/* Admin */
add_action('admin_menu', function(){
    add_menu_page(__('Prisberegner','nordpaa-prisberegner'), __('Prisberegner','nordpaa-prisberegner'),'manage_options','np-pb-v3','np_pb_v3_admin_page','dashicons-calculator',35);
    add_submenu_page('np-pb-v3', __('Lead Dashboard','nordpaa-prisberegner'), __('Leads','nordpaa-prisberegner'),'manage_options','np-pb-v3-leads','np_pb_v3_leads_page');
});
function np_pb_v3_admin_page(){
    if(!current_user_can('manage_options')) return;
    wp_enqueue_style('np-pb-v3-admin', NP_PB_V3_URL.'admin/css/admin.css', array(), NP_PB_V3_VER);
    wp_enqueue_script('np-pb-v3-admin', NP_PB_V3_URL.'admin/js/admin.js', array(), NP_PB_V3_VER, true);
    wp_localize_script('np-pb-v3-admin','npPBv3Admin', array(
        'nonce'=>wp_create_nonce('np_pb_v3_save'),
        'ajax'=>admin_url('admin-ajax.php'),
        'config'=>np_pb_v3_get_config(),
    ));
    echo '<div class="np3-admin-wrap"><div id="np3-admin-app"></div></div>';
}
function np_pb_v3_leads_page(){
    if(!current_user_can('manage_options')) return;
    $leads = (array) get_option('np_pb_v3_leads', array());
    wp_enqueue_style('np-pb-v3-admin', NP_PB_V3_URL.'admin/css/admin.css', array(), NP_PB_V3_VER);
    echo '<div class="np3-admin-wrap"><h1>Lead Dashboard</h1><div class="np3-card">';
    echo '<table class="np3-table"><thead><tr><th>Status</th><th>Navn</th><th>Email</th><th>Telefon</th><th>Firma</th><th>Pris</th><th>Type</th><th>Dato</th></tr></thead><tbody>';
    if(empty($leads)) echo '<tr><td colspan="8" style="text-align:center;color:#6B7285;">Ingen leads endnu</td></tr>';
    foreach(array_reverse($leads) as $L){
        printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($L['status']), esc_html($L['name']), esc_html($L['email']), esc_html($L['phone']), esc_html($L['company']??''),
            esc_html($L['price']), esc_html($L['lead_type']), esc_html($L['date'])
        );
    }
    echo '</tbody></table></div></div>';
}

/* Save/Reset */
add_action('wp_ajax_np_pb_v3_save', function(){
    check_ajax_referer('np_pb_v3_save','nonce');
    if(!current_user_can('manage_options')) wp_send_json_error();
    $p = json_decode(stripslashes($_POST['config'] ?? ''), true);
    if(!$p) wp_send_json_error();
    $cfg = np_pb_v3_get_config();
    $cfg['color'] = sanitize_hex_color($p['color'] ?? $cfg['color']);
    $cfg['yearly_discount'] = floatval($p['yearly_discount'] ?? 0);
    $cfg['bands'] = array();
    foreach((array)($p['bands']??array()) as $b) $cfg['bands'][] = array('label'=>sanitize_text_field($b['label']??''),'min'=>intval($b['min']??0),'max'=>intval($b['max']??0));
    $cfg['modules'] = array();
    foreach((array)($p['modules']??array()) as $m) $cfg['modules'][] = array('name'=>sanitize_text_field($m['name']??''),'desc'=>sanitize_textarea_field($m['desc']??''),'prices'=>array_map('floatval',(array)($m['prices']??array())));
    if(isset($p['branding'])){ $b=$p['branding']; $cfg['branding']=array(
        'company'=>sanitize_text_field($b['company']??''),'logo'=>esc_url_raw($b['logo']??''),
        'primary'=>sanitize_hex_color($b['primary']??'#0B2650'),'secondary'=>sanitize_hex_color($b['secondary']??'#6366f1'),
        'accent'=>sanitize_hex_color($b['accent']??'#ec4899'),'font'=>sanitize_text_field($b['font']??'Inter'),
        'custom_css'=>wp_kses_post($b['custom_css']??''),'powered'=>!empty($b['powered'])
    );}
    update_option('np_pb_v3_config',$cfg);
    wp_send_json_success(array('config'=>$cfg));
});
add_action('wp_ajax_np_pb_v3_reset', function(){
    check_ajax_referer('np_pb_v3_save','nonce');
    if(!current_user_can('manage_options')) wp_send_json_error();
    $cfg=np_pb_v3_demo_config(); update_option('np_pb_v3_config',$cfg); wp_send_json_success(array('config'=>$cfg));
});

/* PDF + Leads */
add_action('wp_ajax_nopriv_np_pb_v3_generate_pdf','np_pb_v3_generate_pdf'); add_action('wp_ajax_np_pb_v3_generate_pdf','np_pb_v3_generate_pdf');
function np_pb_v3_generate_pdf(){
    check_ajax_referer('np_pb_v3_front','nonce');
    $data = json_decode(stripslashes($_POST['payload'] ?? ''), true);
    if(!$data) wp_send_json_error();
    $uploads = wp_upload_dir(); $dir = trailingslashit($uploads['basedir']).'np-pb-v3/'; if(!file_exists($dir)) wp_mkdir_p($dir);
    $file = $dir.'tilbud-'.time().'.pdf';
    require_once NP_PB_V3_PATH.'includes/np_fpdf.php';
    $pdf = new NP_FPDF(); $pdf->AddPage();
    $pdf->Write(6,'Tilbud'); $pdf->Write(6,"\n");
    if(!empty($data['company'])) $pdf->Write(6,'Virksomhed: '.$data['company']."\n");
    if(!empty($data['band_label'])) $pdf->Write(6,'Antal ansatte: '.$data['band_label']."\n");
    $pdf->Write(6,'Fakturering: '.$data['billing']."\n\n");
    $pdf->Write(6,'Valgte moduler:'."\n");
    foreach((array)$data['items'] as $it) $pdf->Write(6,'- '.$it['name'].'  '.$it['price_text']."\n");
    $pdf->Write(6,"\nTotal: ".$data['total_text']."\n");
    $pdf->Output('F',$file);
    $url = trailingslashit($uploads['baseurl']).'np-pb-v3/'.basename($file);
    wp_send_json_success(array('url'=>$url));
}
add_action('wp_ajax_nopriv_np_pb_v3_submit_lead','np_pb_v3_submit_lead'); add_action('wp_ajax_np_pb_v3_submit_lead','np_pb_v3_submit_lead');
function np_pb_v3_submit_lead(){
    check_ajax_referer('np_pb_v3_front','nonce');
    $name=sanitize_text_field($_POST['name']??''); $email=sanitize_email($_POST['email']??''); $phone=sanitize_text_field($_POST['phone']??''); $company=sanitize_text_field($_POST['company']??'');
    $price=sanitize_text_field($_POST['price']??''); $lead_type=sanitize_text_field($_POST['lead_type']??'callback'); $date=current_time('mysql');
    $lead=compact('name','email','phone','company','price','lead_type','date'); $lead['status']='Ny';
    $leads=(array)get_option('np_pb_v3_leads',array()); $leads[]=$lead; update_option('np_pb_v3_leads',$leads);
    wp_mail(get_option('admin_email'),'Nyt lead fra prisberegner',sprintf("Navn: %s\nEmail: %s\nTelefon: %s\nFirma: %s\nPris: %s\nType: %s\nDato: %s\n",$name,$email,$phone,$company,$price,$lead_type,$date));
    wp_send_json_success(array('message'=>'Tak! Vi kontakter dig.'));
}

/* Shortcode */
add_shortcode('nordpaa_prisberegner', function(){
    $cfg=np_pb_v3_get_config();
    wp_enqueue_style('np-pb-v3', NP_PB_V3_URL.'public/css/prisberegner.css', array(), NP_PB_V3_VER);
    wp_enqueue_script('np-pb-v3', NP_PB_V3_URL.'public/js/prisberegner.js', array(), NP_PB_V3_VER, true);
    wp_localize_script('np-pb-v3','npPBv3', array('config'=>$cfg,'ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('np_pb_v3_front')));
    ob_start(); ?>
    <div class="np3-wrap" style="--np3-primary: <?php echo esc_attr($cfg['color']); ?>;"><div id="np3-calc"></div></div>
    <?php return ob_get_clean();
});
