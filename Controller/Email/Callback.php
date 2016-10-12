<?php

namespace Dotdigitalgroup\Email\Controller\Email;

class Callback extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    protected $_helper;
    /**
     * @var \Magento\User\Model\UserFactory
     */
    protected $_adminUser;
    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var \Dotdigitalgroup\Email\Helper\Config
     */
    protected $_config;
    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $_adminHelper;

    /**
     * Callback constructor.
     *
     * @param \Magento\Backend\Helper\Data $backendData
     * @param \Dotdigitalgroup\Email\Helper\Config $config
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Magento\User\Model\UserFactory $adminUser
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     */
    public function __construct(
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Backend\Helper\Data $backendData,
        \Dotdigitalgroup\Email\Helper\Config $config,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\User\Model\UserFactory $adminUser,
        \Magento\Framework\App\Action\Context $context,
        \Dotdigitalgroup\Email\Helper\Data $helper
    ) {
        $this->_customerFactory = $customerFactory;
        $this->_adminHelper = $backendData;
        $this->_config = $config;
        $this->scopeConfig = $scopeConfigInterface;
        $this->_storeManager = $storeManager;
        $this->_adminUser = $adminUser;
        $this->_helper = $helper;

        parent::__construct($context);
    }

    /**
     * Execute method.
     */
    public function execute()
    {
        $code = $this->getRequest()->getParam('code', false);
        $userId = $this->getRequest()->getParam('state');
        //load admin user
        $adminUser = $this->_adminUser->create()
            ->load($userId);
        //app code and admin user must be present
        if ($code && $adminUser->getId()) {
            $clientId = $this->scopeConfig->getValue(
                \Dotdigitalgroup\Email\Helper\Config::XML_PATH_CONNECTOR_CLIENT_ID);
            $clientSecret = $this->scopeConfig->getValue(
                \Dotdigitalgroup\Email\Helper\Config::XML_PATH_CONNECTOR_CLIENT_SECRET_ID);
            //callback uri if not set custom
            $redirectUri = $this->_storeManager->getStore()
                ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true);
            $redirectUri .= 'connector/email/callback';

            $data = 'client_id=' . $clientId .
                '&client_secret=' . $clientSecret .
                '&redirect_uri=' . $redirectUri .
                '&grant_type=authorization_code' .
                '&code=' . $code;

            //callback url
            $url = $this->_config->getTokenUrl();

            //@codingStandardsIgnoreStart
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

            $response = json_decode(curl_exec($ch));

            if ($response === false) {
                $this->_helper->error('Error Number: ' . curl_errno($ch), []);
            }
            if (isset($response->error)) {
                $this->_helper->error('OAUTH failed ' . $response->error, []);
            } elseif (isset($response->refresh_token)) {
                //save the refresh token to the admin user
                $adminUser->setRefreshToken($response->refresh_token)
                    ->save();
            }
            //@codingStandardsIgnoreEnd
        }
        //redirect to automation index page
        $this->_redirect($this->_adminHelper->getUrl('dotdigitalgroup_email/studio'));
    }
}
