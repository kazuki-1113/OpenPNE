<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * sfOpenPNEMailSend
 *
 * @package    OpenPNE
 * @subpackage util
 * @author     Kousuke Ebihara <ebihara@tejimaya.com>
 */
class sfOpenPNEMailSend
{
  public $subject = '';
  public $body = '';

  public function setSubject($subject)
  {
    $this->subject = $subject;
  }

  public function setTemplate($template, $params = array())
  {
    $body = $this->getCurrentAction()->getPartial($template, $params);
    $this->body = $body;
  }

  public function setGlobalTemplate($template, $params = array())
  {
    $template = '_'.$template;
    $view = new opGlobalPartialView(sfContext::getInstance(), 'superGlobal', $template, '');
    $view->setPartialVars($params);
    $body = $view->render();
    $this->body = $body;
  }

  public function send($to, $from)
  {
    return self::execute($this->subject, $to, $from, $this->body);
  }

  public static function getMailTemplate($template, $target = 'pc', $params = array(), $isOptional = true, $context = null)
  {
    if (!$context)
    {
      $context = sfContext::getInstance();
    }

    $view = new sfTemplatingComponentPartialView($context, 'superGlobal', 'notify_mail:'.$target.'_'.$template, '');
    $view->setPartialVars($params);

    if ($isOptional && (!$view->getDirectory() || !is_readable($view->getDirectory().'/'.$view->getTemplate())))
    {
      return '';
    }

    return $view->render();
  }

  public static function sendTemplateMail($template, $to, $from, $params = array())
  {
    if (empty($params['target']))
    {
      $target = opToolkit::isMobileEmailAddress($to) ? 'mobile' : 'pc';
    }
    else
    {
      $target = $params['target'];
    }

    if (in_array($target.'_'.$template, Doctrine::getTable('NotificationMail')->getDisabledNotificationNames()))
    {
      return false;
    }

    $body = self::getMailTemplate($template, $target, $params, false);
    $signature = self::getMailTemplate('signature', $target);
    if ($signature)
    {
      $signature = "\n".$signature;
    }

    return self::execute($params['subject'], $to, $from, $body.$signature);
  }

  public static function execute($subject, $to, $from, $body)
  {
    sfOpenPNEApplicationConfiguration::registerZend();

    $subject = mb_convert_kana($subject, 'KV');

    $mailer = new Zend_Mail('iso-2022-jp');
    $mailer->setHeaderEncoding(Zend_Mime::ENCODING_BASE64)
      ->setFrom($from)
      ->addTo($to)
      ->setSubject(mb_encode_mimeheader($subject, 'iso-2022-jp'))
      ->setBodyText(mb_convert_encoding($body, 'JIS', 'UTF-8'), 'iso-2022-jp', Zend_Mime::ENCODING_7BIT);

    $result = $mailer->send();

    Zend_Loader::registerAutoLoad('Zend_Loader', false);

    return $result;
  }

 /**
  * Gets the current action instance.
  *
  * @return sfAction
  */
  protected function getCurrentAction()
  {
    return sfContext::getInstance()->getController()->getActionStack()->getLastEntry()->getActionInstance();
  }
}
