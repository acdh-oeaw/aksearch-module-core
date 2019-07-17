<?php
/**
 * AK: Extending VuFind Mailer Class
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2019.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  Mailer
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Mailer;

/**
 * AK: Extended VuFind Mailer Class
 *
 * @category AKsearch
 * @package  Mailer
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Mailer extends \VuFind\Mailer\Mailer
{
    /**
     * AK: Send a mime e-mail message. HTML content, attachments and BCC are
     *     possible.
     *
     * @param string|Address|AddressList $to      Recipient email address (or
     * delimited list)
     * @param string|Address             $from    Sender name and email address
     * @param string                     $subject Subject line for message
     * @param string                     $body    Message body (also HTML)
     * @param string|Address|AddressList $replyTo Reply-To address (or delimited
     * list, null for none)
     * @param string                     $cc      CC recipient (null for none)
     * @param string                     $bcc     BCC recipient (null for none)
     * @param                            $atts    Attachments (null for none)
     * 
     * @throws MailException
     * 
     * @return void
     */
    public function sendMimeMail($to, $from, $subject, $body, $replyTo = null,
        $cc = null, $bcc = null, $atts = null
    ) {
        $recipients = $this->convertToAddressList($to);
        $replyTo = $this->convertToAddressList($replyTo);

        // Validate email addresses:
        if ($this->maxRecipients > 0) {
            if ($this->maxRecipients < count($recipients)) {
                throw new MailException('Too Many Email Recipients');
            }
        }
        $validator = new \Zend\Validator\EmailAddress();
        if (count($recipients) == 0) {
            throw new MailException('Invalid Recipient Email Address');
        }
        foreach ($recipients as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException('Invalid Recipient Email Address');
            }
        }
        foreach ($replyTo as $current) {
            if (!$validator->isValid($current->getEmail())) {
                throw new MailException('Invalid Reply-To Email Address');
            }
        }
        $fromEmail = ($from instanceof Address)
            ? $from->getEmail() : $from;
        if (!$validator->isValid($fromEmail)) {
            throw new MailException('Invalid Sender Email Address');
        }

        if (!empty($this->fromAddressOverride)
            && $this->fromAddressOverride != $fromEmail
        ) {
            $replyTo->add($fromEmail);
            if (!($from instanceof Address)) {
                $from = new Address($from);
            }
            $name = $from->getName();
            if (!$name) {
                list($fromPre) = explode('@', $from->getEmail());
                $name = $fromPre ? $fromPre : null;
            }
            $from = new Address($this->fromAddressOverride, $name);
        }

        $mimeMessage = new \Zend\Mime\Message();
        $mimeParts = [];

        // AK: Content part (HTML)
        $htmlPart = new \Zend\Mime\Part($body);
        $htmlPart->type = \Zend\Mime\Mime::TYPE_HTML;
        $htmlPart->charset = 'utf-8';
        $htmlPart->setEncoding(\Zend\Mime\Mime::ENCODING_BASE64);
        $mimeParts[] = $htmlPart;

        // AK: Attachment part(s) if there are any
        if ($atts !== null) {
            foreach ($atts as $att) {
                $fileContents = fopen($att['tmp_name'], 'r');
                $attPart = new \Zend\Mime\Part($fileContents);
                $attPart->type = $att['type'];
                $attPart->filename = $att['name'];
                $attPart->disposition = \Zend\Mime\Mime::DISPOSITION_ATTACHMENT;
                $attPart->encoding = \Zend\Mime\Mime::ENCODING_BASE64;
                $mimeParts[] = $attPart;
            }
        }

        // AK: Set all parts to the mime message
        $mimeMessage->setParts($mimeParts);

        // Convert all exceptions thrown by mailer into MailException objects:
        try {
            // Send message
            $message = $this->getNewMessage()
                ->addFrom($from)
                ->addTo($recipients)
                ->setBody($mimeMessage) // AK: Set mime message as body
                ->setSubject($subject);
            if ($replyTo) {
                $message->addReplyTo($replyTo);
            }
            if ($cc !== null) {
                $message->addCc($cc);
            }
            // AK: Add BCC address
            if ($bcc !== null) {
                $message->addBcc($bcc);
            }
            $this->getTransport()->send($message);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage());
        }
    }


}
