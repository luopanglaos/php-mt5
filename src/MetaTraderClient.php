<?php


namespace Tarikh\PhpMeta;


use Tarikh\PhpMeta\Entities\Trade;
use Tarikh\PhpMeta\Entities\User;
use Tarikh\PhpMeta\Exceptions\ConnectionException;
use Tarikh\PhpMeta\Exceptions\TradeException;
use Tarikh\PhpMeta\Exceptions\UserException;
use Tarikh\PhpMeta\Lib\MTAuthProtocol;
use Tarikh\PhpMeta\Lib\MTConnect;
use Tarikh\PhpMeta\Lib\MTLogger;
use Tarikh\PhpMeta\Lib\MTRetCode;
use Tarikh\PhpMeta\Lib\MTTradeProtocol;
use Tarikh\PhpMeta\Lib\MTUser;
use Tarikh\PhpMeta\Lib\MTUserProtocol;
use Tarikh\PhpMeta\Lib\MTOrderProtocol;
use Tarikh\PhpMeta\src\Lib\MTEnDealAction;
use Tarikh\PhpMeta\Lib\MTHistoryProtocol;

//+------------------------------------------------------------------+
//--- web api version
define("WebAPIVersion", 2190);
//--- web api date
define("WebAPIDate", "18 Oct 2019");

class MetaTraderClient
{
    /**
     * @var MTConnect $m_connect
     */
    protected $m_connect;
    //--- name agent
    private $m_agent = 'WebAPI';
    //--- is set crypt connection
    private $m_is_crypt = true;

    protected $server;

    protected $port;

    protected $username;

    protected $password;
    /**
     * @var bool
     */
    private $debug;

    public function __construct($server, $port, $username, $password, $debug = false)
    {

        $file_path = 'logs/';
        $this->m_agent = "WebAPI";
        $this->m_is_crypt = true;
        MTLogger::Init($this->m_agent, $debug, $file_path);
        $this->server = $server;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->debug = $debug;
    }

    public function connect()
    {
        $ip = $this->server;
        $port = $this->port;
        $login = $this->username;
        $password = $this->password;
        $timeout = 3000;

        //--- create connection class
        $this->m_connect = new MTConnect($ip, $port, $timeout, $this->m_is_crypt);
        // dd($login, $password);
        //--- create connection
        if (($error_code = $this->m_connect->Connect()) != MTRetCode::MT_RET_OK) return $error_code;
        //--- authorization to MetaTrader 5 server
        $auth = new MTAuthProtocol($this->m_connect, $this->m_agent);
        //---
        $crypt_rand = '';
        if (($error_code = $auth->Auth($login, $password, $this->m_is_crypt, $crypt_rand)) != MTRetCode::MT_RET_OK) {
            //--- disconnect
            $this->disconnect();
            return $error_code;
        }
        //--- if need crypt
        if ($this->m_is_crypt) $this->m_connect->SetCryptRand($crypt_rand, $password);
        //---
        return MTRetCode::MT_RET_OK;
    }

    /**
     * Check connection
     * @return bool
     */
    public function isConnected()
    {
        return $this->m_connect != null;
    }

    /**
     * Disconnect from MetaTrader 5 server
     * @return void
     */
    public function disconnect()
    {
        if ($this->m_connect) $this->m_connect->Disconnect();
    }

    /**
     * Create trade record such as Deposit or Withdrawal
     * @param Trade $trade
     * @return Trade
     * @throws ConnectionException
     * @throws TradeException
     */
    public function trade(Trade $trade): Trade
    {
        if (!$this->isConnected()) {
            $conn = $this->connect();
            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_trade = new MTTradeProtocol($this->m_connect);
        $ticket = null;

        $call = $mt_trade->TradeBalance($trade->getLogin(), $trade->getType(), $trade->getAmount(), $trade->getComment(), $ticket);
        if ($call != MTRetCode::MT_RET_OK) {
            throw new TradeException(MTRetCode::GetError($call));
        }
        $trade->setTicket($ticket);
        return $trade;
    }

    /**
     * Create new User
     * @param User $user
     * @return User
     * @throws ConnectionException
     * @throws UserException
     */
    public function createUser(User $user): User
    {
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_user = new MTUserProtocol($this->m_connect);
        $mtUser = MTUser::CreateDefault();
        $mtUser->Group = $user->getGroup();
        $mtUser->Name = $user->getName();
        $mtUser->Email = $user->getEmail();
        $mtUser->Address = $user->getAddress();
        $mtUser->City = $user->getCity();
        $mtUser->State = $user->getState();
        $mtUser->Country = $user->getCountry();
        $mtUser->MainPassword = $user->getMainPassword();
        $mtUser->Phone = $user->getPhone();
        $mtUser->PhonePassword = $user->getPhonePassword();
        $mtUser->InvestPassword = $user->getInvestorPassword();
        $mtUser->Group = $user->getGroup();
        $mtUser->Leverage = $user->getLeverage();
        $mtUser->ZipCode = $user->getZipCode();

        $newMtUser = MTUser::CreateDefault();
        $result = $mt_user->Add($mtUser, $newMtUser);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        $user->setLogin($newMtUser->Login);
        return $user;
    }

    /**
     * Get list users login
     *
     * @param string $group
     * @return MTRetCode
     * @throws ConnectionException
     * @throws UserException
     */
    public function getUserLogins($group)
    {
        $logins = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }

        $mt_user = new MTUserProtocol($this->m_connect);
        $result = $mt_user->UserLogins($group, $logins);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $logins;
    }

    /**
     * Get User Information By Login
     * @param $login
     * @return MTUser
     * @throws ConnectionException
     * @throws UserException
     */
    public function getUser($login)
    {
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_user = new MTUserProtocol($this->m_connect);
        $result = $mt_user->Get($login, $user);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $user;
    }

    /**
     * Delete user by login
     * @param $login
     * @return bool
     * @throws ConnectionException
     * @throws UserException
     */
    public function deleteUser($login)
    {
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_user = new MTUserProtocol($this->m_connect);
        $result = $mt_user->Delete($login, $user);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return true;
    }

    /**
     * Get Order Details
     * @param $ticket
     * @return int
     * @throws ConnectionException
     * @throws UserException
     */
    public function getOrder($ticket)
    {
        $order = 0;
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTOrderProtocol($this->m_connect);
        $result = $mt_order->OrderGet($ticket, $order);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $order;
    }

    /**
     * Get Total Order
     * @param $login
     * @return int
     * @throws ConnectionException
     * @throws UserException
     */
    public function getOrderTotal($login)
    {
        $total = 0;
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTOrderProtocol($this->m_connect);
        $result = $mt_order->OrderGetTotal($login, $total);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $total;
    }

    /**
     * Get Open Order Pagination
     * @param $login
     * @param $offset
     * @param $total
     * @return null
     * @throws ConnectionException
     * @throws UserException
     */
    public function getOrderPagination($login, $offset, $total)
    {
        $orders = null;
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTOrderProtocol($this->m_connect);
        $result = $mt_order->OrderGetPage($login, $offset, $total, $orders);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $orders;
    }

    /**
     * Conduct User balance
     * @param $login
     * @param MTEnDealAction $type
     * @param $balance
     * @param $comment
     * @return null
     * @throws ConnectionException
     * @throws UserException
     */
    public function conductUserBalance($login, MTEnDealAction $type, $balance, $comment)
    {
        $ticket = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTTradeProtocol($this->m_connect);
        $result = $mt_order->TradeBalance($login, $type, $balance, $comment);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $ticket;
    }

    /**
     * @param $user
     * @return MTUser
     * @throws ConnectionException
     * @throws UserException
     */
    public function updateUser($user)
    {
        $newUser = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_user = new MTUserProtocol($this->m_connect);
        $result = $mt_user->Update($user, $newUser);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $newUser;
    }

    /**
     * Get Total Closed Order
     * @param $login
     * @return int
     * @throws ConnectionException
     * @throws UserException
     */
    public function getOrderHistoryTotal($login, $from, $to)
    {
        $total = 0;
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTHistoryProtocol($this->m_connect);
        $result = $mt_order->HistoryGetTotal($login, $from, $to, $total);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $total;
    }

    /**
     * Get Closed Order Pagination
     * @param $login
     * @param $offset
     * @param $total
     * @return null
     * @throws ConnectionException
     * @throws UserException
     */
    public function getOrderHistoryPagination($login, $from, $to, $offset, $total)
    {
        $orders = null;
        $user = null;
        if (!$this->isConnected()) {
            $conn = $this->connect();

            if ($conn != MTRetCode::MT_RET_OK) {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_order = new MTHistoryProtocol($this->m_connect);
        $result = $mt_order->HistoryGetPage($login, $from, $to, $offset, $total, $orders);
        if ($result != MTRetCode::MT_RET_OK) {
            throw new UserException(MTRetCode::GetError($result));
        }
        return $orders;
    }


}
