<?php
class SpotPage_reportpost extends SpotPage_Abs {
	private $_inReplyTo;
	private $_reportForm;
	
	function __construct(SpotDb $db, SpotSettings $settings, $currentSession, $params) {
		parent::__construct($db, $settings, $currentSession);
		$this->_reportForm = $params['reportform'];
		$this->_inReplyTo = $params['inreplyto'];
	} # ctor

	function render() {
		$formMessages = array('errors' => array(),
							  'info' => array());
							  
		# Controleer de users' rechten
		$this->_spotSec->fatalPermCheck(SpotSecurity::spotsec_report_spam, '');
				
		# Sportparser is nodig voor het escapen van de random string
		$spotParser = new SpotParser();
		
		# spot signing is nodig voor het RSA signen van de spot en dergelijke
		$spotSigning = new SpotSigning();
		
		# creeer een default report
		$report = array('body' => 'Dit is SPAM!',
						 'inreplyto' => $this->_inReplyTo,
						 'newmessageid' => '',
						 'randomstr' => '');
		
		# reportpost verzoek was standaard niet geprobeerd
		$postResult = array();
		
		# zet de page title
		$this->_pageTitle = "report: report spot";

		# Als de user niet ingelogged is, dan heeft dit geen zin
		if ($this->_currentSession['user']['userid'] == SPOTWEB_ANONYMOUS_USERID) {
			$postResult = array('result' => 'notloggedin');
			unset($this->_reportForm['submitpost']);
		} # if

		# Zorg er voor dat reserved usernames geen reports kunnen posten
		$spotUser = new SpotUserSystem($this->_db, $this->_settings);
		if (!$spotUser->validUsername($this->_currentSession['user']['username'])) {
			$postResult = array('result' => 'notloggedin');
			unset($this->_reportForm['submitpost']);
		} # if
		
		if (isset($this->_reportForm['submitpost'])) {
			# Notificatiesysteem initialiseren
			$spotsNotifications = new SpotNotifications($this->_db, $this->_settings, $this->_currentSession);

			# submit unsetten we altijd
			unset($this->_reportForm['submitpost']);
			
			# zorg er voor dat alle variables ingevuld zijn
			$report = array_merge($report, $this->_reportForm);

			# vraag de users' privatekey op
			$this->_currentSession['user']['privatekey'] = 
				$this->_db->getUserPrivateRsaKey($this->_currentSession['user']['userid']);
			
			# het messageid krijgen we met <>'s, maar we werken 
			# in spotweb altijd zonder, dus die strippen we
			$report['newmessageid'] = substr($report['newmessageid'], 1, -1);
			
			# valideer of we dit report kunnen posten, en zo ja, doe dat dan
			$spotPosting = new SpotPosting($this->_db, $this->_settings);
			$formMessages['errors'] = $spotPosting->reportSpotAsSpam($this->_currentSession['user'], $report);
			
			if (empty($formMessages['errors'])) {
				$postResult = array('result' => 'success');

				# en verstuur een notificatie
				$spotsNotifications->sendReportPosted($report['inreplyto']);
			} else {
				$postResult = array('result' => 'failure');
			} # else
		} # if
		
		#- display stuff -#
		$this->template('spamreport', array('postreportform' => $report,
											 'formmessages' => $formMessages,
											 'postresult' => $postResult));
	} # render	
} # class SpotPage_reportpost