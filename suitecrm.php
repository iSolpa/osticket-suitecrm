<?php

require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.osticket.php');
include_once(INCLUDE_DIR . 'class.client.php');
require_once(INCLUDE_DIR . 'class.config.php');
require_once(INCLUDE_DIR . 'class.format.php');
require_once('config.php');
require_once('suitecrm-api.php');

class SuiteCRMPlugin extends Plugin {

    var $config_class = "SuiteCRMPluginConfig";
    var $api = new SuiteCRMAPI();
    /**
     * The entrypoint of the plugin, keep short, always runs.
     */
    function bootstrap() {
        // Listen for osTicket to tell us it's made a new ticket or updated
        // an existing ticket:
        Signal::connect('ticket.created', array($this, 'onTicketCreated'));
        //Signal::connect('threadentry.created', array($this, 'onTicketUpdated'));
        // Tasks? Signal::connect('task.created',array($this,'onTaskCreated'));
    }

    /**
     * What to do with a new Ticket?
     *
     * @global OsticketConfig $cfg
     * @param Ticket $ticket
     * @return type
     */
    function onTicketCreated(Ticket $ticket) {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("SuiteCRM plugin called too early.");
            return;
        }

        // Convert any HTML in the message into text
        $plaintext = Format::html2text($ticket->getMessages()[0]->getBody()->getClean());

        $this->sendToSuiteCRM($ticket);
    }

    /**
     * A helper function that gets the account id.
     *
     * @param string $email
     * @throws \Exception
     */
    function getSuiteCRMAccount($email) {
      $url = "/api/v8/modules/Contacts/relationships/accounts?filter[Email]=$email&include=Contact.Account.id";
      $json = $this->api->apiClient($url);
      $result = json_decode($json)->data;
      return $result[0]['id']['relationships']['accounts']['data'][0]['id'];
    }

    /**
     * A helper function that creates a case using the suitecrm json api.
     *
     * @global osTicket $ost
     * @global OsticketConfig $cfg
     * @param Ticket $ticket
     * @param string $heading
     * @param string $body
     * @param string $colour
     * @throws \Exception
     */
    function sendToSuiteCRM(Ticket $ticket) {
        $endpoint = '/api/v8/modules/Cases/';

        $api->setBaseUrl($this->getConfig()->get('suitecrm-api-url'));
        $api->setClientId($this->getConfig()->get('suitecrm-api-id'));
        $api->setClientSecret($this->getConfig()->get('suitecrm-api-secret'));
        $api->login();

        //we need to get owner info
        //$owner = $ticket->getOwner();
        $email = $ticket->getEmail();
        $account_id = $this->getSuiteCRMAccount($email);

        $case_status = "";
        if($state = "Open")
          $case_status = "Open_New";
        elseif($state = "Closed")
          $case_status = "Closed_Closed";

        $data = array(
            "data" => array(
              "id"=>"",
              "name"=>$ticket->getSubject(),
              "date_entered"=>$ticket->getCreateDate(),
              "date_modified"=>$ticket->getUpdateDate(),
              "description"=>$ticket->getLastMessage(),
              "resolution"=>"",
              "case_number"=>$ticket->getNumber(),
              "type"=>"Helpdesk",
              "state"=>$ticket->getTopic(),
              "status"=>$case_status,
              "priority"=>$ticket->getPriority(),
              "relationships"=>array(
                "accounts"=>array(
                  "data"=>array(
                    "id"=>$account_id,
                    "type"=>"Account",
                    "meta"=>array(
                      "middle_table"=>array(
                        "data"=>array(
                          "id"=>"",
                          "type"=>"Link",
                          "attributes"=>array(
                            "accept_status"=>"accept",
                            "user_id"=>$account_id
                          )
                        )
                      )
                    )
                  )
                )
              )
            )
        );
    }

    /**
     * What to do with an Updated Ticket?
     *
     * @global OsticketConfig $cfg
     * @param ThreadEntry $entry
     * @return type
     */
    function onTicketUpdated(ThreadEntry $entry) {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Slack plugin called too early.");
            return;
        }
        if (!$entry instanceof MessageThreadEntry) {
            // this was a reply or a system entry.. not a message from a user
            return;
        }

        // Need to fetch the ticket from the ThreadEntry
        $ticket = $this->getTicket($entry);
        if (!$ticket instanceof Ticket) {
            // Admin created ticket's won't work here.
            return;
        }

        // Check to make sure this entry isn't the first (ie: a New ticket)
        $first_entry = $ticket->getMessages()[0];
        if ($entry->getId() == $first_entry->getId()) {
            return;
        }
        // Convert any HTML in the message into text
        $plaintext = Format::html2text($entry->getBody()->getClean());

        // Format the messages we'll send
        $heading = sprintf('%s CONTROLSTART%sscp/tickets.php?id=%d|#%sCONTROLEND %s'
                , __("Ticket")
                , $cfg->getUrl()
                , $ticket->getId()
                , $ticket->getNumber()
                , __("updated"));
        $this->sendToSuiteCRM($ticket, $heading, $plaintext, 'warning');
    }

    /**
     * Fetches a ticket from a ThreadEntry
     *
     * @param ThreadEntry $entry
     * @return Ticket
     */
    function getTicket(ThreadEntry $entry) {
        $ticket_id = Thread::objects()->filter([
                    'id' => $entry->getThreadId()
                ])->values_flat('object_id')->first() [0];

        // Force lookup rather than use cached data..
        // This ensures we get the full ticket, with all
        // thread entries etc..
        return Ticket::lookup(array(
                    'ticket_id' => $ticket_id
        ));
    }

    /**
     * Formats text according to the
     * formatting rules:https://api.slack.com/docs/message-formatting
     *
     * @param string $text
     * @return string
     */
    function format_text($text) {
        $formatter      = [
            '<' => '&lt;',
            '>' => '&gt;',
            '&' => '&amp;'
        ];
        $formatted_text = str_replace(array_keys($formatter), array_values($formatter), $text);
        // put the <>'s control characters back in
        $moreformatter  = [
            'CONTROLSTART' => '<',
            'CONTROLEND'   => '>'
        ];
        // Replace the CONTROL characters, and limit text length to 500 characters.
        return substr(str_replace(array_keys($moreformatter), array_values($moreformatter), $formatted_text), 0, 500);
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    function get_gravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array()) {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
        return $url;
    }

}
