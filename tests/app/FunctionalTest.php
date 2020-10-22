<?php

namespace App\Database;

use App\Controllers\Home;
use App\Controllers\User;
use App\Entities\Host;
use App\Entities\Login;
use App\Libraries\SendGridEmail;
use App\Libraries\VirtualMinShell;
use App\Models\HostModel;
use App\Models\LoginModel;
use CodeIgniter\Test\CIDatabaseTestCase;
use Config\Services;

class FunctionalTest extends CIDatabaseTestCase
{
    protected $namespace  = null;

    public function testRegister()
    {
        // check database migration and prepare controller
        $this->assertTrue($this->db->tableExists('login'));
        $this->assertTrue($this->db->table('login')->countAllResults() === 0);
        ($home = new Home())->initController($req = Services::request(), Services::response(), Services::logger());
        $req->setMethod('post');
        $req->setGlobal('post', $login_data = [
            'name' => 'Contoso User',
            'email' => 'contoso@example.com',
            'password' => 'mycontosouser',
        ]);
        $req->setGlobal('request', $login_data);

        // execute register and check email sent

        $home->register();
        /** @var Login */
        $login = (new LoginModel())->find(1);
        $this->assertTrue($login !== null);
        $this->assertTrue($login->otp !== null);
        $this->assertTrue(SendGridEmail::$sentEmail === 'verify_email');
        $sentEmail = json_decode(SendGridEmail::$sentBody)->personalizations[0]->to[0]->email ?? '';
        $this->assertTrue($sentEmail === $login->email);

        // okay, check if we can verify

        $req->setMethod('get');
        $req->setGlobal('get', $otp_data = [
            'code' => base64_encode("$login->email:$login->otp"),
        ]);
        $req->setGlobal('request', $otp_data);
        $home->verify();
        /** @var Login */
        $login = (new LoginModel())->find(1);
        $this->assertTrue($login->otp === null);
        $this->assertTrue($login->trustiness === 1);
        $this->assertTrue($login->email_verified_at !== null);

        // now check login

        $req->setMethod('post');
        $req->setGlobal('post', $login_data);
        $req->setGlobal('request', $login_data);
        $home->login() && $this->assertTrue(Services::session()->login === 1);
    }

    public function testCreateFreeAndUpgradeHost()
    {
        (new LoginModel())->register([
            'name' => 'Contoso User',
            'email' => 'contoso@example.com',
            'password' => 'mycontosouser',
        ], true, true);
        ($user = new User())->initController($req = Services::request(), Services::response(), Services::logger());

        // Check create host

        $req->setMethod('post');
        $req->setGlobal('post', $post_data = [
            'plan' => 1,
            'server' => 1,
            'username' => 'contoso',
            'password' => 'mycontoso',
        ]);
        $req->setGlobal('request', $post_data);
        $user->host('create');
        /** @var Host */
        $host = (new HostModel())->find(1);
        $this->assertEquals(array_intersect_key($host->toRawArray(), array_flip(
            [
                'id', 'login_id', 'username', 'domain', 'password',
                'status', 'server_id', 'plan_id'
            ]
        )), [
            'id' => 1,
            'login_id' => 1,
            'username' => 'contoso',
            'domain' => 'contoso.dom.my.id',
            'password' => 'mycontoso',
            'status' => 'active',
            'server_id' => 1,
            'plan_id' => 1,
        ]);
        $this->assertEquals(explode("\n", trim(VirtualMinShell::$output)), [
            'program=create-domain&user=contoso&pass=mycontoso&email=contoso@example.com' .
                '&domain=contoso.dom.my.id&plan=Freedom&limits-from-plan=&dir=&webmin=' .
                '&virtualmin-nginx=&virtualmin-nginx-ssl=&unix='
        ]);
        VirtualMinShell::$output = '';

        // Okay, try rename



        // Okay, try extend

        $req->setMethod('post');
        $req->setGlobal('post', $post_data = [
            'mode' => 'new',
            'plan' => 1,
            'years' => 1,
        ]);
        $req->setGlobal('request', $post_data);
        $user->host('upgrade', $host->id);
        $newexp = (new HostModel())->find(1)->expiry_at;
        $this->assertTrue(strtotime($host->expiry_at) <= strtotime($newexp));

        // Okay, try upgrade

        $req->setGlobal('post', $post_data = [
            'mode' => 'new',
            'plan' => 2,
            'years' => 1,
        ]);
        $req->setGlobal('request', $post_data);
        $user->host('upgrade', $host->id);
        /** @var Host */
        $host = (new HostModel())->find(1);
        $this->assertTrue(($purchase = $host->purchase)->status === 'pending');

        // Try to execute payment

        ($home = new Home())->initController($req, Services::response(), Services::logger());
        $req->setGlobal('get', [
            'id' => $purchase->id,
            'challenge' => $purchase->metadata->_challenge,
            'secret' => $req->config->ipaymuSecret,
        ]);
        $req->setGlobal('post', [
            'trx_id' => 123,
            'via' => 'Test',
            'status' => 'berhasil',
        ]);
        $home->notify();
        $this->assertTrue(($purchase = $host->purchase)->status === 'active');
        $this->assertEquals(explode("\n", trim(VirtualMinShell::$output)), [
            'program=enable-domain&domain=contoso.dom.my.id',
            'program=modify-domain&domain=contoso.dom.my.id&apply-plan=Lite'
        ]);
        VirtualMinShell::$output = '';
    }

    public function testCreateHostWithDomain()
    {
        (new LoginModel())->register([
            'name' => 'Contoso User',
            'email' => 'contoso@example.com',
            'password' => 'mycontosouser',
        ], true, true);
        ($user = new User())->initController($req = Services::request(), Services::response(), Services::logger());


        // Check create host

        $req->setMethod('post');
        $req->setGlobal('post', $post_data = [
            'plan' => 2,
            'server' => 1,
            'username' => 'contoso',
            'password' => 'mycontoso',
            'years' => 1,
            'addons' => 5,
            'domain' => json_encode([
                'scheme' => 1,
                'name' => 'example',
                'bio' => [
                    'fname' => 'Contoso',
                    'company' => 'Contoso Company',
                    'email' => 'contoso@example.com',
                    'tel' => '15556667',
                    'country' => 'US',
                    'state' => 'California',
                    'city' => 'Vancouver',
                    'postal' => '11101',
                    'address1' => 'St. John',
                ]
            ])
        ]);
        $req->setGlobal('request', $post_data);
        $user->host('create');
        /** @var Host */
        $host = (new HostModel())->find(1);
        $this->assertTrue(isset($host, $host->purchase, $host->domain_detail));
        $purchase = $host->purchase;
        $domain = $host->domain_detail;
        $meta = $purchase->metadata;
        $this->assertTrue($domain->status === 'pending' && $host->status === 'pending');
        $this->assertEquals($meta->toRawArray(), [
            'type' => "hosting",
            'price' => 17,
            'price_unit' => "usd",
            'template' => "",
            'expiration' =>  $meta->expiration,
            'years' =>  1,
            'plan' => 2,
            'addons' => 5,
            '_challenge' =>  $meta->_challenge,
            '_id' => NULL,
            '_via' =>  NULL,
            '_issued' => $meta->_issued,
            '_invoiced' => null,
            '_status' => null,
            'registrar' => [
                'domain' => "example.com",
                'periode' => 1,
                'ns1' => "nsp.dom.my.id",
                'ns2' => "nss.dom.my.id",
                'fname' => "Contoso",
                'company' => "Contoso Company",
                'address1' => "St. John",
                'city' => "Vancouver",
                'state' => "California",
                'country' => "US",
                'postcode' => "11101",
                'phonenumber' => "15556667",
                'email' => "contoso@example.com",
                'user_username' => "contoso@example.com",
                'user_fname' => "Contoso",
                'user_company' => "Contoso Company",
                'user_address' => "St. John",
                'user_city' => "Vancouver",
                'user_province' => "California",
                'user_country' => "US",
                'user_postal_code' => "11101",
                'autoactive' => "on",
            ]
        ]);

        // Try to execute payment

        ($home = new Home())->initController($req, Services::response(), Services::logger());
        $req->setGlobal('get', [
            'id' => $purchase->id,
            'challenge' => $meta->_challenge,
            'secret' => $req->config->ipaymuSecret,
        ]);
        $req->setGlobal('post', [
            'trx_id' => 123,
            'via' => 'Test',
            'status' => 'berhasil',
        ]);
        $home->notify();
        $host = (new HostModel())->find(1);
        $purchase = $host->purchase;
        $domain = $host->domain_detail;
        $meta = $purchase->metadata;
        $this->assertTrue($domain->status === 'active', $host->status === 'active');

    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->resetDataCache();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $req = Services::request();
        $req->setMethod('get');
        $req->setGlobal('get', []);
        $req->setGlobal('post', []);
        $req->setGlobal('request', []);
    }
}