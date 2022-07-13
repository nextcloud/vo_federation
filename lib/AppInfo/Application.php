<?php
/**
 * Nextcloud - VO Federation
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\VO_Federation\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

/**
 * Class Application
 *
 * @package OCA\VO_Federation\AppInfo
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'vo_federation';
	public const DEFAULT_AAI_CONSUMER_KEY = 'nextcloud';
	//public const DEFAULT_AAI_CONSUMER_KEY = 'publicplan_voapp_primary';
    public const DEFAULT_AAI_CONSUMER_SECRET = '09e3c268-d8bc-42f1-b7c6-74d307ef5fde';
    //public const DEFAULT_AAI_CONSUMER_SECRET = '9sxvwHDegbojv5OffdSspnu0Z2OmMEaR7viCFovT14Jj95LCQZ';

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
    }
}

