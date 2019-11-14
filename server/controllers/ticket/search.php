<?php
use Respect\Validation\Validator as DataValidator;
use RedBeanPHP\Facade as RedBean;
DataValidator::with('CustomValidations', true);


/**
 * @api {post} /ticket/search Search tickets
 * @apiVersion 4.5.0
 *
 * @apiName Search ticket
 *
 * @apiGroup Ticket
 *
 * @apiDescription This path search specific tickets.
 *
 * @apiPermission user
 *
 * @apiParam {Number[]} tags The ids of the tags to make a custom search.
 * @apiParam {Number} closed The status of closed 1 or 0 to make a custom search.
 * @apiParam {Number} unreadStaff The status of unread_staff 1 or 0 to make a custom search.
 * @apiParam {Number[]} priority The values of priority to make a custom search.
 * @apiParam {Number[]} dateRange The numbers of the range of date to make a custom search.
 * @apiParam {Number[]} departments The ids of the departments to make a custom search.
 * @apiParam {Object[]} authors A object {id,staff} with id and boolean to make a custom search.
 * @apiParam {Number} assigned The status of assigned 1 or 0 to make a custom search.
 * @apiParam {String} query A string to find into a ticket to make a custom search.
 * @apiParam {Number} page The number of the page of the tickets.
 * @apiParam {Object} orderBy A object {value, asc}with string and boolean to make a especific order of the  search.
 *
 * @apiUse NO_PERMISSION
 * @apiUse INVALID_TAG_FILTER
 * @apiUse INVALID_CLOSED_FILTER
 * @apiUse INVALID_UNREAD_STAFF_FILTER
 * @apiUse INVALID_PRIORITY_FILTER
 * @apiUse INVALID_DATE_RANGE_FILTER
 * @apiUse INVALID_DEPARTMENT_FILTER
 * @apiUse INVALID_AUTHOR_FILTER
 * @apiUse INVALID_ASSIGNED_FILTER
 * @apiUse INVALID_ORDER_BY
 * @apiUse INVALID_PAGE
 *
 * @apiSuccess {Object} data Empty object
 *
 *

 */

class SearchController extends Controller {
    const PATH = '/search';
    const METHOD = 'POST';

    public function validations() {
        return [
            'permission' => 'any',
            'requestData' => [
                'page' => [
                    'validation' => DataValidator::oneOf(DataValidator::numeric()->positive(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_PAGE
                ],
                'tags' => [
                    'validation' => DataValidator::oneOf(DataValidator::validTagsId(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_TAG_FILTER
                ],
                'closed' => [
                    'validation' => DataValidator::oneOf(DataValidator::in(['0','1']),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_CLOSED_FILTER
                ],
                'unreadStaff' => [
                    'validation' => DataValidator::oneOf(DataValidator::in(['0','1']),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_UNREAD_STAFF_FILTER
                ],
                'priority' => [
                    'validation' => DataValidator::oneOf(DataValidator::validPrioritys(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_PRIORITY_FILTER
                ],
                'dateRange' => [
                    'validation' => DataValidator::oneOf(DataValidator::validDateRange(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_DATE_RANGE_FILTER
                ],
                'departments' => [
                    'validation' => DataValidator::oneOf(DataValidator::validDepartmentsId(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_DEPARTMENT_FILTER
                ],
                'authors' => [
                    'validation' => DataValidator::oneOf(DataValidator::validAuthorsId(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_AUTHOR_FILTER
                ],
                'assigned' => [
                    'validation' => DataValidator::oneOf(DataValidator::in(['0','1']),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_ASSIGNED_FILTER
                ],
                'orderBy' => [
                    'validation' => DataValidator::oneOf(DataValidator::ValidOrderBy(),DataValidator::nullType()),
                    'error' => ERRORS::INVALID_ORDER_BY
                ],
            ]
        ];
    }

    public function handler() {
        $inputs = [
            'closed' => Controller::request('closed'),
            'tags' => json_decode(Controller::request('tags')),
            'unreadStaff' => Controller::request('unreadStaff'),
            'priority' => json_decode(Controller::request('priority')),
            'dateRange' => json_decode(Controller::request('dateRange')),
            'departments' => json_decode(Controller::request('departments')),
            'authors' => json_decode(Controller::request('authors'),true),
            'assigned' => Controller::request('assigned'),
            'query' => Controller::request('query'),
            'orderBy' => json_decode(Controller::request('orderBy'),true),
            'page' => Controller::request('page')
        ];
        $query = $this->getSQLQuery($inputs);
        $queryWithOrder = $this->getSQLQueryWithOrder($inputs);

        throw new Exception("SELECT COUNT(*) FROM (SELECT COUNT(*) " . $query . " ) AS T2");
        $totalCount = RedBean::getAll("SELECT COUNT(*) FROM (SELECT COUNT(*) " . $query . " ) AS T2")[0]['COUNT(*)'];
        $ticketList = RedBean::getAll($queryWithOrder);

        Response::respondSuccess([
            'tickets' => $ticketList,
            'pages' => ceil($totalCount / 10),
            'page' => Controller::request('page')
        ]);

    }

    public function getSQLQuery($inputs) {
        $query = "FROM (ticket LEFT JOIN tag_ticket ON tag_ticket.ticket_id = ticket.id LEFT JOIN ticketevent ON ticketevent.ticket_id = ticket.id)";
        $filters = "";
        $this->setQueryFilters($inputs, $filters);
        $query .= $filters . " GROUP BY ticket.id";
        return $query;
    }

    public function getSQLQueryWithOrder($inputs) {
        $query = $this->getSQLQuery($inputs);
        $order = "";
        $query = "SELECT ticket.id,ticket.title,ticket.ticket_number,ticket.content ,ticketevent.content " . $query;

        $this->setQueryOrder($inputs, $order);
        $inputs['page'] ?  $page =  $inputs['page'] : $page  = 1 ;
        $query .= $order ." LIMIT 10 OFFSET " . (($page-1)*10);
        return $query;
    }

    //FILTER
    private function setQueryFilters($inputs, &$filters){
        if(array_key_exists('tags',$inputs)) $this->setTagFilter($inputs['tags'], $filters);
        if(array_key_exists('closed',$inputs)) $this->setClosedFilter($inputs['closed'], $filters);
        if(array_key_exists('assigned',$inputs)) $this->setAssignedFilter($inputs['assigned'], $filters);
        if(array_key_exists('unreadStaff',$inputs)) $this->setSeenFilter($inputs['unreadStaff'], $filters);
        if(array_key_exists('priority',$inputs)) $this->setPriorityFilter($inputs['priority'], $filters);
        if(array_key_exists('dateRange',$inputs)) $this->setDateFilter($inputs['dateRange'], $filters);
        if(array_key_exists('departments',$inputs)) $this->setDepartmentFilter($inputs['departments'], $filters);
        if(array_key_exists('authors',$inputs)) $this->setAuthorFilter($inputs['authors'], $filters);
        if(array_key_exists('query',$inputs)) $this->setStringFilter($inputs['query'], $filters);
        if($filters != "") $filters =  " WHERE " . $filters;
    }

    private function setTagFilter($tagList, &$filters){
        if($tagList){
            $filters != "" ? $filters .= " and " : null;

            foreach($tagList as $key => $tag) {

                $key == 0 ? $filters .= " ( " : null;
                ($key != 0 && $key != sizeof($tagList)) ? $filters .= " or " : null;

                $filters .= "tag_ticket.tag_id  = " . $tag ;
            }
            $filters .= ")";
        }
    }
    public function setClosedFilter($closed, &$filters){
       if ($closed != null) {
            if ($filters != "")  $filters .= " and ";
            $filters .= "ticket.closed = " . $closed ;
        }
    }
    private function setSeenFilter($unreadStaff, &$filters){
       if ($unreadStaff != null) {
            if ($filters != "")  $filters .= " and ";
            $filters .= "ticket.unread_staff = " . $unreadStaff;
        }
    }
    private function setPriorityFilter($prioritys, &$filters){
        if($prioritys != null){
            if ($filters != "")  $filters .= " and ";
            foreach(array_unique($prioritys) as $key => $priority) {

                $key == 0 ? $filters .= " ( " : null;
                ($key != 0 && $key != sizeof($prioritys)) ? $filters .= " or " : null;

                if($priority == 0){
                    $filters .= "ticket.priority = " . "'low'";
                }elseif($priority == 1){
                    $filters .= "ticket.priority = " . "'medium'";
                }elseif($priority == 2){
                    $filters .= "ticket.priority = " . "'high'";
                }

                $key == sizeof($prioritys) ? $filters .= " ) " : null ;
            }
            $prioritys != "" ? $filters .= ") " : null;
        }
    }

    private function setDateFilter($dateRange, &$filters){
       if ($dateRange != null) {
            if ($filters != "")  $filters .= " and ";

            foreach($dateRange as $key => $date) {
                $key == 0 ? ($filters .= "(ticket.date >= " . $date ): ($filters .= " and ticket.date <= " . $date . ")");
            }
        }
    }

    private function setDepartmentFilter($departments, &$filters){
        if($departments != null){
            if ($filters != "")  $filters .= " and ";

            foreach($departments as $key => $department) {

                $key == 0 ? $filters .= " ( " : null;
                ($key != 0 && $key != sizeof($departments)) ? $filters .= " or " : null;

                $filters .= "ticket.department_id = " . $department ;
            }
            $filters .= ")";
        }
    }

    private function setAuthorFilter($authors, &$filters){
        if($authors != null){

            if ($filters != "")  $filters .= " and ";

            foreach($authors as $key => $author){

                $key == 0 ? $filters .= " ( " : null;
                ($key != 0 && $key != sizeof($authors)) ? $filters .= " or " : null;

                if($author['staff']){
                    $filters .= "ticket.author_staff_id  = " . $author['id'];
                } else {
                    $filters .= "ticket.author_id = " . $author['id'];
                }
            }

            $filters .= ")";

        }
    }

    private function setAssignedFilter($assigned, &$filters){
       if($assigned != null){
            if ($filters != "")  $filters .= " and ";
            $key = "";
            $assigned == 0 ? $key = "IS NULL" : $key = "IS NOT NULL";
            $filters .= "ticket.owner_id " . $key;
       }
    }

    private function setStringFilter($search, &$filters){
        if($search != null){
            if ($filters != "")  $filters .= " and ";
            $filters .= " (ticket.title LIKE '%" . $search . "%' or ticket.content LIKE '%" . $search . "%' or ticket.ticket_number LIKE '%" . $search . "%' or (ticketevent.type = 'COMMENT' and ticketevent.content LIKE '%" . $search ."%'))";
        };
    }

    //ORDER
    private function setQueryOrder($inputs, &$order){
        $order =  " ORDER BY ";
        if(array_key_exists('query',$inputs)) $this->setStringOrder($inputs['query'], $order);
        if(array_key_exists('orderBy',$inputs)) $this->setEspecificOrder($inputs['orderBy'], $order);
        $order .=  "ticket.closed asc, ticket.owner_id asc, ticket.unread_staff asc, ticket.priority desc, ticket.date desc ";
    }
    private function setEspecificOrder($orderBy, &$order){
        if($orderBy != null){
            $orientation = ($orderBy['asc'] ? " asc" : " desc" );
            $order .= "ticket." . $orderBy['value'] . $orientation . ",";
        };
    }
    private function setStringOrder($querysearch, &$order){
        if($querysearch != null){
            $order .= "CASE WHEN (ticket.ticket_number LIKE '%" . $querysearch ."%') THEN ticket.ticket_number END desc,CASE WHEN (ticket.title LIKE '%" . $querysearch ."%') THEN ticket.title END desc, CASE WHEN ( ticket.content LIKE '%" . $querysearch ."%') THEN ticket.content END desc, CASE WHEN (ticketevent.type = 'COMMENT' and ticketevent.content LIKE '%".$querysearch."%') THEN ticketevent.content END desc," ;
        }
    }

}
