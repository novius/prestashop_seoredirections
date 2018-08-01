<?php
/**
 * NOVIUS.
 *
 * @copyright  2018 Novius
 * @see http://www.novius.com
 */
if (! defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__).'/classes/RedirectionModel.php';

class Prestashop_SEORedirections extends Module
{
    protected $_html = '';

    protected static $imported_filename = 'redirections_import.csv';
    protected static $csv_separator = ';';

    public function __construct()
    {
        $this->name = 'prestashop_seoredirections';
        $this->tab = 'seo';
        $this->version = '0.1.0';
        $this->author = 'Novius';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('SEO redirections');
        $this->description = $this->l('Make as much as you want 301 or 302 redirections on your shop.');
    }

    public function install()
    {
        return parent::install() && RedirectionModel::createRedirectionTable() && $this->registerHook('actionDispatcher');
    }

    public function getContent()
    {
        $this->postProcess();
        if (Tools::isSubmit('saveredirection') || Tools::isSubmit('update'.$this->name) || Tools::isSubmit('add')) {
            $this->_html .= $this->renderForm();
        } else {
            $this->_html .= $this->renderFormImport();
            $this->_html .= $this->renderList();
        }

        return $this->_html;
    }

    protected function postProcess()
    {
        if (Tools::isSubmit('saveredirection')) {
            $arr_errors = $this->validateProcess();
            if (count($arr_errors)) {
                $this->_html .= $this->displayError(implode('<br>', $arr_errors));
            } else {
                if (! Tools::getValue('id_redirection')) {
                    $this->createRedirectionFromPostValues();
                } else {
                    $this->updateRedirectionFromPostValues();
                }
            }
        } elseif (Tools::isSubmit('import_redirection')) {
            $this->processUploadCsv();
        } elseif (Tools::isSubmit('delete'.$this->name)) {
            $this->deleteRedirection();
        }

        return true;
    }

    protected function validateProcess()
    {
        $arr_errors = [];

        $old_url = Tools::getValue('old_url', '');
        if (empty($old_url) || ! Validate::isAbsoluteUrl($old_url)) {
            $arr_errors[] = $this->l('Invalid old URL.');
        }

        $new_url = Tools::getValue('new_url', '');
        if (empty($new_url) || ! Validate::isAbsoluteUrl($new_url)) {
            $arr_errors[] = $this->l('Invalid new URL.');
        }

        $redirection_type = Tools::getValue('redirection_type', '');
        if (empty($redirection_type) || ! RedirectionModel::isValidRedirectionType($redirection_type)) {
            $arr_errors[] = $this->l('Invalid redirection type.');
        }

        return $arr_errors;
    }

    protected function createRedirectionFromPostValues()
    {
        $redirection = new RedirectionModel();
        $redirection->new_url = Tools::getValue('new_url');
        $redirection->old_url = Tools::getValue('old_url');
        $redirection->redirection_type = Tools::getValue('redirection_type');
        $redirection->save();

        if ($redirection->id) {
            Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=3');
        } else {
            $this->_html .= $this->displayError(
                $this->l('Error during redirection saving.')
            );
        }
    }

    protected function updateRedirectionFromPostValues()
    {
        $redirection = new RedirectionModel((int) Tools::getValue('id_redirection'));
        if (! $redirection->id) {
            $this->_html .= $this->displayError(
                $this->l('Unable to find redirection item.')
            );

            return;
        }
        $redirection->new_url = Tools::getValue('new_url');
        $redirection->old_url = Tools::getValue('old_url');
        $redirection->redirection_type = Tools::getValue('redirection_type');

        if ($redirection->save()) {
            Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=4');
        } else {
            $this->_html .= $this->displayError(
                $this->l('Error during redirection update.')
            );
        }
    }

    protected function deleteRedirection()
    {
        $id_redirection = (int) Tools::getValue('id_redirection');
        if (! $id_redirection) {
            $this->_html .= $this->displayError(
                $this->l('Unable to find redirection item.')
            );
        } else {
            $redirection = new RedirectionModel($id_redirection);
            if (! $redirection->id) {
                $this->_html .= $this->displayError(
                    $this->l('Unable to find redirection item.')
                );
            } else {
                if ($redirection->delete()) {
                    $this->_html .= $this->displayConfirmation(
                        $this->l('Redirection successfully deleted.')
                    );
                } else {
                    $this->_html .= $this->displayError(
                        $this->l('Unable to delete redirection item.')
                    );
                }
            }
        }
    }

    public function renderList()
    {
        $this->fields_list = [
            'id_redirection' => [
                'title' => $this->l('ID'),
                'type' => 'text',
                'search' => false,
                'orderby' => false,
            ],
            'old_url' => [
                'title' => $this->l('Old URL'),
                'type' => 'text',
                'search' => false,
                'orderby' => false,
            ],
            'new_url' => [
                'title' => $this->l('New URL'),
                'width' => 140,
                'type' => 'text',
                'search' => false,
                'orderby' => false,
            ],
            'redirection_type' => [
                'title' => $this->l('Redirection type'),
                'width' => 50,
                'align' => 'text-center',
                'type' => 'text',
                'search' => false,
                'orderby' => false,
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier = 'id_redirection';
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;

        $helper->toolbar_btn['new'] = [
            'href' => AdminController::$currentIndex.'&configure='.$this->name.'&add&token='.Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Add new'),
        ];

        $helper->icon = 'icon-info-circle';
        $helper->title = $this->displayName;
        $helper->table = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $totalRedirections = RedirectionModel::getTotalRedirections();
        $helper->listTotal = $totalRedirections;

        /* Determine total page number */
        $perPage = $helper->_default_pagination;
        if (in_array((int) Tools::getValue($helper->table.'_pagination'), $helper->_pagination)) {
            $perPage = (int) Tools::getValue($helper->table.'_pagination');
        } elseif (isset($this->context->cookie->{$helper->table.'_pagination'}) && $this->context->cookie->{$helper->table.'_pagination'}) {
            $perPage = $this->context->cookie->{$helper->table.'_pagination'};
        }
        $total_pages = max(1, ceil($helper->listTotal / $perPage));
        $page = (int) Tools::getValue('submitFilter'.$helper->table);
        if (! $page) {
            $page = 1;
        }
        if ($page > $total_pages) {
            $page = $total_pages;
        }
        $offset = ($page - 1) * $perPage;

        $redirection_list = RedirectionModel::getListRedirections($perPage, $offset);

        return $helper->generateList($redirection_list, $this->fields_list);
    }

    protected function renderFormImport()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (! is_dir(static::getPathImport()) || ! is_writable(static::getPathImport())) {
            $this->_html .= $this->displayWarning(
                $this->l('This module is not writable. Please make it writable to import redirections from csv file.')
            );
        }

        $this->fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Import redirections'),
            ],
            'input' => [
                [
                    'type' => 'file',
                    'label' => $this->l('CSV file'),
                    'name' => 'csv_import',
                    'desc' => $this->l('File must contains two columns : the first is absolute Old Url, the second is absolute New Url.')
                        .'<br />'
                        .$this->l('Columns separator must be').' "'.static::$csv_separator.'"'
                        .'<br />'
                        .'<a href="'.$this->_path.'files/redirections_import_example.csv" title="" target="_blank">'.$this->l('Example of file to import').'</a>',
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Skip first line'),
                    'name' => 'csv_skip_first_line',
                    'desc' => $this->l('Skip the first line of csv file ?'),
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Import'),
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'seoredirections';
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->submit_action = 'import_redirection';
        $helper->show_toolbar = true;
        $helper->fields_value = [
            'csv_skip_first_line' => 0,
        ];

        return $helper->generateForm($this->fields_form);
    }

    protected function processUploadCsv()
    {
        $file_name_input = 'csv_import';

        if (! is_dir(static::getPathImport()) || ! is_writable(static::getPathImport())) {
            $this->_html .= $this->displayError(
                $this->l('This module is not writable. Please make it writable to import redirections from csv file.')
            );

            return;
        }

        if (isset($_FILES[$file_name_input]) && ! empty($_FILES[$file_name_input]['error'])) {
            switch ($_FILES[$file_name_input]['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->_html .= $this->displayError(
                        $this->l('The uploaded file exceeds the upload_max_filesize directive in php.ini. If your server configuration allows it, you may add a directive in your .htaccess.')
                    );
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->_html .= $this->displayError(
                        $this->l('The uploaded file exceeds the post_max_size directive in php.ini. If your server configuration allows it, you may add a directive in your .htaccess.')
                    );
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->_html .= $this->displayError(
                        $this->l('The uploaded file was only partially uploaded.')
                    );
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->_html .= $this->displayError(
                        $this->l('No file was uploaded.')
                    );
                    break;
            }
        } elseif (! preg_match('/.*\.csv$/i', $_FILES[$file_name_input]['name'])) {
            $this->_html .= $this->displayError(
                $this->l('The extension of your file should be .csv.')
            );
        } elseif (! move_uploaded_file($_FILES[$file_name_input]['tmp_name'], static::getPathImport().static::$imported_filename)) {
            $this->_html .= $this->displayError(
                $this->l('An error occurred while uploading / copying the file.')
            );
        } else {
            Tools::chmodr(static::getPathImport().static::$imported_filename, 0664);
            $this->importRedirectionsFromFile();
        }
    }

    protected function importRedirectionsFromFile()
    {
        $b_error = false;
        $skip_first_line = (int) Tools::getValue('csv_skip_first_line', 0);
        $path_file = static::getPathImport().static::$imported_filename;
        if (! is_file($path_file) || ! is_readable($path_file)) {
            $this->_html .= $this->displayError(
                $this->l('Unable to read imported file.')
            );

            return;
        }

        $file_handler = fopen($path_file, 'r');

        if ($skip_first_line) {
            fgetcsv($file_handler, 1000, static::$csv_separator);
        }

        while ($columns = fgetcsv($file_handler, 1000, static::$csv_separator)) {
            if (count($columns) > 2) {
                $this->_html .= $this->displayError(
                    $this->l('CSV file must contains only 2 columns.')
                );
                $b_error = true;
                break;
            }

            $old_url = trim($columns[0]);
            $new_url = trim($columns[1]);

            if (empty($old_url) || ! Validate::isAbsoluteUrl($old_url)) {
                continue;
            }
            if (empty($new_url) || ! Validate::isAbsoluteUrl($new_url)) {
                continue;
            }

            $redirection = new RedirectionModel();
            $redirection->old_url = $old_url;
            $redirection->new_url = $new_url;
            $redirection->redirection_type = '301';
            $redirection->save();
        }

        if (! $b_error) {
            $this->_html .= $this->displayConfirmation(
                $this->l('File successfully imported')
            );
        }

        if (is_file($path_file)) {
            unlink($path_file);
        }
    }

    protected function renderForm()
    {
        $id_redirection = (int) Tools::getValue('id_redirection');
        $helper = $this->initForm();

        if ($id_redirection) {
            $redirection = new RedirectionModel($id_redirection);
            $helper->fields_value['old_url'] = $redirection->old_url;
            $helper->fields_value['new_url'] = $redirection->new_url;
            $helper->fields_value['redirection_type'] = $redirection->redirection_type;
            $helper->fields_value['id_redirection'] = $redirection->id;
        } else {
            $helper->fields_value['old_url'] = Tools::getValue('old_url', '');
            $helper->fields_value['new_url'] = Tools::getValue('new_url', '');
            $helper->fields_value['redirection_type'] = Tools::getValue('redirection_type', '');
            $helper->fields_value['id_redirection'] = '';
        }

        return $helper->generateForm($this->fields_form);
    }

    protected function initForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $this->fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('New redirection'),
            ],
            'input' => [
                [
                    'type' => 'hidden',
                    'name' => 'id_redirection',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Old URL'),
                    'name' => 'old_url',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('New URL'),
                    'name' => 'new_url',
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Redirection type'),
                    'name' => 'redirection_type',
                    'options' => [
                        'query' => RedirectionModel::getRedirectionsTypes(),
                        'id' => 'type',
                        'name' => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'seoredirections';
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->submit_action = 'saveredirection';
        $helper->show_toolbar = true;
        $helper->back_url = AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules');
        $helper->show_cancel_button = true;

        return $helper;
    }

    public function hookActionDispatcher($params)
    {
        $controller_type = isset($params['controller_type']) ? $params['controller_type'] : null;
        if ($controller_type == Dispatcher::FC_FRONT || $controller_type == Dispatcher::FC_MODULE) {

            // Disable redirections if REQUEST is POST
            if (! empty($_POST)) {
                return;
            }

            $base_uri = Tools::getShopDomainSsl(true);
            $base_uri .= '/';
            if (mb_substr($base_uri, -1, 1) == '/') {
                $base_uri = mb_substr($base_uri, 0, mb_strlen($base_uri) - 1);
            }

            $current_uri = $_SERVER['REQUEST_URI'];
            if (mb_substr($current_uri, 0, 1) != '/') {
                $current_uri = '/'.$current_uri;
            }

            $old_url = $base_uri.$current_uri;

            // Search redirection with exact pattern
            $result = RedirectionModel::findRedirectionByOldUrl($old_url);
            if (! $result) {
                $parsed_url = parse_url($old_url);
                // Do a new search only if there are GET parameters in previous OLD URL search
                if (! empty($parsed_url['query'])) {
                    $old_url = $base_uri.$parsed_url['path'];
                    $result = RedirectionModel::findRedirectionByOldUrl($old_url, true);
                }
            }

            if ($result) {
                $new_url = $result['new_url'];
                $redirection_infos = RedirectionModel::getRedirectionTypeInfos($result['redirection_type']);
                if (! empty($redirection_infos)) {
                    header($redirection_infos['header']);
                    header("Location: $new_url");
                    exit(0);
                }
            }
        }
    }

    protected static function getPathImport()
    {
        return dirname(__FILE__).DIRECTORY_SEPARATOR.'import'.DIRECTORY_SEPARATOR;
    }
}
