<?php
require_once "CB393389C.php";
class MoyDom_MB393389C {
	public $plugin_file=__FILE__;
	public $responseObj;
	public $licenseMessage;
	public $showMessage=false;
	public $slug="realty";
	function __construct() {
		add_action( 'admin_print_styles', [ $this, 'SetAdminStyle' ] );
		$licenseKey=get_option("MoyDom_lic_Key","");
		$liceEmail=get_option( "MoyDom_lic_email","");
		$templateDir=get_template_directory(); //or dirname(__FILE__);
		if(CB393389C::CheckWPPlugin($licenseKey,$liceEmail,$this->licenseMessage,$this->responseObj,$templateDir."/style-rtl.css")){
			add_action( 'admin_menu', [$this,'ActiveAdminMenu'],99999);
			add_action( 'admin_post_MoyDom_el_deactivate_license', [ $this, 'action_deactivate_license' ] );
			//$this->licenselMessage=$this->mess;

			define( 'DISABLE_ULTIMATE_GOOGLE_MAP_API', true );

			if ( ! isset( $content_width ) ) {
			    $content_width = 1920;
			}

			require_once get_template_directory() . '/includes/class-myhome.php';

			function My_Home_Theme() {
			    return My_Home::get_instance();
			}

			// initiate MyHome theme
			My_Home_Theme()->init();

		}else{
			if(!empty($licenseKey) && !empty($this->licenseMessage)){
				$this->showMessage=true;
			}
			update_option("MoyDom_lic_Key","") || add_option("MoyDom_lic_Key","");
			add_action( 'admin_post_MoyDom_el_activate_license', [ $this, 'action_activate_license' ] );
			add_action( 'admin_menu', [$this,'InactiveMenu']);
		}
        }
	function SetAdminStyle() {
		wp_register_style( "MoyDomLic", get_theme_file_uri("_lic_style.css"),10);
		wp_enqueue_style( "MoyDomLic" );
	}
	function ActiveAdminMenu(){

	add_menu_page (  "Realty", "Realty", "activate_plugins", $this->slug, [$this,"Activated"], "dashicons-star-filled");
	//add_submenu_page(  $this->slug, "Realty License", "License Info", "activate_plugins",  $this->slug."_license", [$this,"Activated"] );

	}
	function InactiveMenu() {
		add_menu_page( "Realty", "Realty", 'activate_plugins', $this->slug,  [$this,"LicenseForm"], "dashicons-star-filled" );

	}
	function action_activate_license(){
		check_admin_referer( 'el-license' );
		$licenseKey=!empty($_POST['el_license_key'])?$_POST['el_license_key']:"";
		$licenseEmail=!empty($_POST['el_license_email'])?$_POST['el_license_email']:"";
		update_option("MoyDom_lic_Key",$licenseKey) || add_option("MoyDom_lic_Key",$licenseKey);
		update_option("MoyDom_lic_email",$licenseEmail) || add_option("MoyDom_lic_email",$licenseEmail);
		update_option('_site_transient_update_themes','');
		wp_safe_redirect(admin_url( 'admin.php?page='.$this->slug));
	}
	function action_deactivate_license() {
		check_admin_referer( 'el-license' );
		$message="";
		if(CB393389C::RemoveLicenseKey(__FILE__,$message)){
			update_option("MoyDom_lic_Key","") || add_option("MoyDom_lic_Key","");
			update_option('_site_transient_update_themes','');
		}
    	wp_safe_redirect(admin_url( 'admin.php?page='.$this->slug));
    }
	function Activated(){
		?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="MoyDom_el_deactivate_license"/>
            <div class="el-license-container">
                <h3 class="el-license-title"><i class="dashicons-before dashicons-star-filled"></i> <?php _e("Realty Лицензии",$this->slug);?> </h3>
                <hr>
                <ul class="el-license-info">
                <li>
                    <div>
                        <span class="el-license-info-title"><?php _e("Статус",$this->slug);?></span>

                        <?php if ( $this->responseObj->is_valid ) : ?>
                            <span class="el-license-valid"><?php _e("Активная",$this->slug);?></span>
                        <?php else : ?>
                            <span class="el-license-valid"><?php _e("Неактивная",$this->slug);?></span>
                        <?php endif; ?>
                    </div>
                </li>

                <li>
                    <div>
                        <span class="el-license-info-title"><?php _e("Тип лицензии",$this->slug);?></span>
                        <?php echo $this->responseObj->license_title; ?>
                    </div>
                </li>

               <li>
                   <div>
                       <span class="el-license-info-title"><?php _e("Действительна до",$this->slug);?></span>
                       <?php echo $this->responseObj->expire_date;
                       if(!empty($this->responseObj->expire_renew_link)){
                           ?>
                           <a target="_blank" class="el-blue-btn" href="<?php echo $this->responseObj->expire_renew_link; ?>">Renew</a>
                           <?php
                       }
                       ?>
                   </div>
               </li>

               <li>
                   <div>
                       <span class="el-license-info-title"><?php _e("Техподдержка",$this->slug);?></span>
                       <?php
                           echo $this->responseObj->support_end;
                        if(!empty($this->responseObj->support_renew_link)){
                            ?>
                               <a target="_blank" class="el-blue-btn" href="<?php echo $this->responseObj->support_renew_link; ?>">Renew</a>
                            <?php
                        }
                       ?>
                   </div>
               </li>
                <li>
                    <div>
                        <span class="el-license-info-title"><?php _e("Ключ лицензии",$this->slug);?></span>
                        <span class="el-license-key"><?php echo esc_attr( substr($this->responseObj->license_key,0,9)."XXXXXXXX-XXXXXXXX".substr($this->responseObj->license_key,-9) ); ?></span>
                    </div>
                </li>
                </ul>
                <div class="el-license-active-btn">
                    <?php wp_nonce_field( 'el-license' ); ?>
                    <?php submit_button('Отключить'); ?>
                </div>
            </div>
        </form>
		<?php
	}

	function LicenseForm() {
		?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="MoyDom_el_activate_license"/>
            <div class="el-license-container">
                <h3 class="el-license-title"><i class="dashicons-before dashicons-star-filled"></i> <?php _e("Лицензия Риэлти",$this->slug);?></h3>
                <hr>
				<?php
					if(!empty($this->showMessage) && !empty($this->licenseMessage)){
						?>
                        <div class="notice notice-error is-dismissible">
                            <p><?php echo $this->licenseMessage; ?></p>
                        </div>
						<?php
					}
				?>

    		    <div class="el-license-field">
    			    <label for="el_license_key"><?php _e("Код лицензии",$this->slug);?></label>
    			    <input type="text" class="regular-text code" name="el_license_key" size="50" placeholder="xxxxxxxx-xxxxxxxx-xxxxxxxx-xxxxxxxx" required="required">
    		    </div>
                <div class="el-license-field">
                    <label for="el_license_key"><?php _e("Email Адрес",$this->slug);?></label>
                    <?php
                        $purchaseEmail   = get_option( "MoyDom_lic_email", get_bloginfo( 'admin_email' ));
                    ?>
                    <input type="text" class="regular-text code" name="el_license_email" size="50" value="<?php echo $purchaseEmail; ?>" placeholder="" required="required">
                    <div><small><?php _e("На этот адрес будут отправляться новости об обновлении продукта.",$this->slug);?></small></div>
                </div>
                <div class="el-license-active-btn">
					<?php wp_nonce_field( 'el-license' ); ?>
					<?php submit_button('Активировать'); ?>
                </div>
            </div>
        </form>
		<?php
	}
}

new MoyDom_MB393389C();



