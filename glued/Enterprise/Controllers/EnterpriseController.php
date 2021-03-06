<?php

declare(strict_types=1);

namespace Glued\Enterprise\Controllers;

use Carbon\Carbon;
use \Opis\JsonSchema\Loaders\File as JSL;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Symfony\Component\DomCrawler\Crawler;
use Glued\Enterprise\Classes\Utils as EnterpriseUtils;

class EnterpriseController extends AbstractTwigController
{

    // ==========================================================
    // PROJECTS UI
    // ==========================================================

    public function projects_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        $projects = $this->db->get('t_enterprise_projects', null, ['c_uid as id', 'c_json->>"$.name" as name', 'c_json->>"$.description" as description']);
        return $this->render($response, 'Enterprise/Views/projects.twig', [
            'domains' => $domains,
            'projects' => $projects
        ]);
    }


    public function opportunities_list_ui(Request $request, Response $response, array $args = []): Response {
        // Since we don't have RBAC implemented yet, we're passing all domains
        // to the view. The view uses them in the form which adds/modifies a view.
        // 
        // TODO - write a core function that will get only the domains for a given user
        // so that we dont copy paste tons of code around and we don't present sources out of RBAC
        // scope of a user.
        // 
        // TODO - preseed domains on installation with at least one domain
        $domains = $this->db->get('t_core_domains');
        return $this->render($response, 'Enterprise/Views/opportunities.twig', [
            'domains' => $domains,
        ]);
    }


    public function project_detail_ui(Request $request, Response $response, array $args = []): Response {
        $project_id = (int)$args['uid'];
        $this->db->where('c_uid', $project_id);
        $data = $this->db->getOne('t_enterprise_projects', ['c_uid as id', 'c_json as json', 'c_json->>"$.name" as name', 'c_json->>"$.description" as description', 'c_json->>"$.flags" as flags']);
        
      $jsf_schema   = file_get_contents(__ROOT__.'/glued/Enterprise/Controllers/Schemas/projects.v1.schema');
      $jsf_uischema = file_get_contents(__ROOT__.'/glued/Enterprise/Controllers/Schemas/projects.v1.formui');
      $jsf_formdata = $data['json'];
      $cilova_adresa = $this->routerParser->urlFor('enterprise.projects.api01', ['uid' => $project_id]);//'https://japex01.vaizard.xyz'.
      $navratova_adresa = $this->routerParser->urlFor('enterprise.projects.object', ['uid' => $project_id]);//'https://japex01.vaizard.xyz'.
      $jsf_onsubmit = '
        $.ajax({
          url: "'.$cilova_adresa.'",
          dataType: "text",
          type: "PATCH",
          data: "stockdata=" + JSON.stringify(formData.formData),
          success: function(data) {
            // diky replacu nezustava puvodni adresa v historii, takze se to vice blizi redirectu
            // presmerovani na editacni stranku se vraci z toho ajaxu
            window.location.replace("'.$navratova_adresa.'");
            /*
            ReactDOM.render((<div><h1>Thank you</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre></div>), 
                     document.getElementById("main"));
            */
          },
          error: function(xhr, status, err) {
            ReactDOM.render((<div><h1>Something goes wrong ! not saving.</h1><pre>{JSON.stringify(formData.formData, null, 2) }</pre></div>), 
                     document.getElementById("main"));
          }
        });
      ';
        
        return $this->render($response, 'Enterprise/Views/projects.object.twig', [
            'data' => $data,
            'json_schema_output' => $jsf_schema,
            'json_uischema_output' => $jsf_uischema,
            'json_formdata_output' => $jsf_formdata,
            'json_onsubmit_output' => $jsf_onsubmit,
            'json_custom_structure' => true
        ]);
    }



    // ==========================================================
    // PROJECTS API
    // ==========================================================


     public function projects_get_api(Request $request, Response $response, array $args = []): Response {
      
        $q = $request->getQueryParams();
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $uid = $args['uid'] ?? null;
        $filter = $q['filter'] ?? '';
        $data = null;

        $query = "
          SELECT 
          JSON_ARRAYAGG(
            JSON_MERGE(
              p1.c_json,
              JSON_OBJECT(
                'dt_created', p1.c_ts_created,
                'dt_updated', p1.c_ts_updated,
                'parent', JSON_OBJECT(
                  'id', r.c_parent,
                  'name', p2.c_name
                )
              )
            )
          ) as c_json
          FROM (`t_enterprise_projects` p1
          LEFT JOIN `t_enterprise_projects_rels` r
          ON (r.c_child = p1.c_uid))
          LEFT JOIN `t_enterprise_projects` p2
          ON (r.c_parent = p2.c_uid)
          ";
       
        if ($uid) {
            $query .= ' WHERE p1.c_uid = ?';
            $params = [ $uid ];
        } else {
            $query .= ' WHERE p1.c_name LIKE ?';
            $params = [ '%'.$filter.'%' ];
        }

      $data = json_decode($this->db->rawQuery($query, $params)[0]['c_json'] ?? '', true);
      if (($uid) and (!$data)) $payload = $builder->withCode(404)->build();
      else $payload = $builder->withData((array)$data)->withCode(200)->build();
      return $response->withJson($payload);
    }

    public function projects_patch(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        
        // id z adresy
        $project_id = (int)$args['uid'];
        
        // Get patch data
        $patch_data = $request->getParsedBody();
        $req = $patch_data['stockdata'];
        
        //$req['user'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        //$req['id'] = (int)$args['uid'];
        // user a id se nemeni pri updatu. ale pridame patched
        //$req['patched'] = 1;
        
        // flags zbooleanujeme
        /*
        $req['flags']['order'] = $req['flags']['order'] == 'true'?true:false;
        $req['flags']['task'] = $req['flags']['task'] == 'true'?true:false;
        $req['flags']['event'] = $req['flags']['event'] == 'true'?true:false;
        */
        
        // convert body to object
        //$doc = json_decode(json_encode((object)$req));
        $doc = json_decode($req);
        
        /*
        // Get old data
        $this->db->where('c_uid', $req['id']);
        $doc = $this->db->getOne('t_enterprise_projects', ['c_json'])['c_json'];
        if (!$doc) { throw new HttpBadRequestException( $request, __('Bad source ID.')); }
        $doc = json_decode($doc);

        // TODO replace this lame acl with something propper.
        if($doc->user != $req['user']) { throw new HttpForbiddenException( $request, 'You can only edit your own calendar sources.'); }

        // Patch old data
        $doc->description = $req['description'];
        $doc->name = $req['name'];
        $doc->type = $req['type'];
        $doc->color = $req['color'];
        $doc->icon = $req['icon'];
        $doc->domain = (int)$req['domain'];
        if (array_key_exists('config', $req) and ($req['config'] != "")) {
          $config = json_decode(trim($req['config']), true);
          if (json_last_error() !== 0) throw new HttpBadRequestException( $request, __('Config contains invalid json.'));
          $doc->config = (object)$config;
        } else { $doc->config = new \stdClass(); }
        if (!array_key_exists('currency', $req)) { $doc->currency = ''; } else {  $doc->currency = $req['currency']; }

        // TODO if $doc->domain is patched here, you have to first test, if user has access to the domain
        */
        // load the json schema and validate data against it
        $loader = new JSL("schema://enterprise/", [ __ROOT__ . "/glued/Enterprise/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://enterprise/projects.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($doc, $schema);
        if ($result->isValid()) {
            $row = [ 'c_json' => json_encode($doc) ];
            $this->db->where('c_uid', $project_id);
            $id = $this->db->update('t_enterprise_projects', $row);
            if (!$id) { throw new HttpInternalServerErrorException( $request, __('Updating of the account failed.')); }
        } else { throw new HttpBadRequestException( $request, __('Invalid account data.')); }
        
        // nejaka flash message
        $this->flash->addMessage('info', 'tak sme to updatovali');
        
        // Success
        $payload = $builder->withData((array)$doc)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }


    public function projects_post(Request $request, Response $response, array $args = []): Response {
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $req = $request->getParsedBody();
        
        $puvodni_data = $req;
        
        $req['user'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $req['id'] = 0;
        $req['_v'] = (int) 1;
        $req['_s'] = 'enterprise.projects';
        
        $parent = (int) $req['parent'];
        unset($req['parent']);  // protoze neni ve schematu
        
        // flags zbooleanujeme
        $req['flags']['order'] = $req['flags']['order'] == 'true'?true:false;
        $req['flags']['task'] = $req['flags']['task'] == 'true'?true:false;
        $req['flags']['event'] = $req['flags']['event'] == 'true'?true:false;
        
        // convert body to object
        $puvodni_json = json_encode((object)$req);
        $req = json_decode(json_encode((object)$req));
        
        // TODO replace manual coercion above with a function to recursively cast types of object values according to the json schema object (see below)       
        
        // load the json schema and validate data against it
        $loader = new JSL("schema://enterprise/", [ __ROOT__ . "/glued/Enterprise/Controllers/Schemas/" ]);
        $schema = $loader->loadSchema("schema://enterprise/projects.v1.schema");
        $result = $this->jsonvalidator->schemaValidation($req, $schema);

        if ($result->isValid()) {
            $row = array (
                'c_json' => json_encode($req)
            );
            try { $req->id = $this->utils->sql_insert_with_json('t_enterprise_projects', $row); } catch (Exception $e) { 
                throw new HttpInternalServerErrorException($request, $e->getMessage());  
            }
            
            // meli bysme prenastavit id v ulozenych datech
            // ale asi uz to tam je z sql_insert_with_json somehow
            
            // pokud je nastaveny parent > 0, vlozime
            if ($parent > 0) {
                $data = Array (
                "c_parent" => $parent,
                "c_child" => $req->id
                );
                $this->db->insert ('t_enterprise_projects_rels', $data);
            }
            
            $payload = $builder->withData((array)$req)->withCode(200)->build();
            return $response->withJson($payload, 200);
        }
        else {
            $struktura_dat = array();
            $struktura_dat['puvodni'] = print_r($puvodni_data, true);
            $struktura_dat['json'] = $puvodni_json;
            $reseed = $request->getParsedBody();
            $payload = $builder->withData($struktura_dat)
                                ->withValidationReseed($reseed)
                               ->withValidationError($result->getErrors())
                               ->withCode(400)
                               ->build();
            return $response->withJson($payload, 400);
        }
    }


    public function projects_delete(Request $request, Response $response, array $args = []): Response {
        try { 
          $this->db->where('c_uid', (int)$args['uid']);
          $this->db->delete('t_enterprise_projects');
        } catch (Exception $e) { 
          throw new HttpInternalServerErrorException($request, $e->getMessage());  
        }
        $builder = new JsonResponseBuilder('enterprise.projects', 1);
        $req = $request->getParsedBody();
        $req['user'] = (int)$GLOBALS['_GLUED']['authn']['user_id'];
        $req['id'] = (int)$args['uid'];
        $payload = $builder->withData((array)$req)->withCode(200)->build();
        return $response->withJson($payload, 200);
    }
}
