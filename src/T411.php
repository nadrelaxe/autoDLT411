<?php
require_once __DIR__.'/acurl.class.php';

class T411
{
    /**
     * Objet acurl
     * @var acurl
     */
    private $acurl = null;

    /**
     * URL racine de l'API de t411
     * @var string
     */
    private $apiRoot = 'http://api.t411.ai';

    /**
     * Nom d'utilisateur d'un compte t411
     * @var string
     */
    private $username = '';

    /**
     * Mot de passe associé au compte t411
     * @var string
     */
    private $password = '';

    /**
     * Constructeur :-))
     *
     * @param array      $config Tableau de configuration
     * @param acurl|null $acurl  Object acurl ou null
     */
    public function __construct(array $config = [], acurl $acurl = null)
    {
        $this->username = isset($config['username']) ? trim($config['username']) : null;
        $this->password = isset($config['password']) ? trim($config['password']) : null;

        // Création d'un nouvel objet s'il n'existe pas
        $this->acurl = ($acurl === null ? new acurl() : $acurl);

    }

    /**
     * Permet de retourner une exception s'il y a eu une erreur pendant une requête
     * @param  stdClass $answer Objet JSON
     */
    private function checkErrors($answer)
    {
        if(isset($answer->error))
        {
            throw new Exception($answer->error, $answer->code);
        }

    }

    /**
     * Permet à l'utilisateur de se connecter à l'API
     *
     * @return true
     */
    public function login()
    {
        if(empty($this->username))
        {
            throw new Exception("Le nom d'utilisateur ne peut être vide", 1);
        }

        if(empty($this->password))
        {
            throw new Exception("Le mot de passe ne peut être vide", 1);
        }

        $answer = $this->auth();

        $this->checkErrors($answer);

        $this->uid   = $answer->uid;
        $this->token = $answer->token;
        $this->patchAuthorization();

        return true;
    }

    /**
     * Envoie la requête d'authentification à l'API
     * @return stdClass Objet JSON
     */
    private function auth()
    {
        return json_decode(
            $this->acurl->http_post_request([
                'username' => $this->username,
                'password' => $this->password,
            ],
            $this->apiRoot.'/auth')
        );
    }

    /**
     * Rajoute un header "Authorization" à l'objet acurl
     */
    private function patchAuthorization()
    {
        curl_setopt($this->acurl->getHandler(), CURLOPT_HTTPHEADER, [
            'Authorization: '.$this->token
        ]);
    }

    /**
     * Facilicite l'envoi, le traitement, ainsi que la gestion d'erreur d'une requête à l'API
     * @param  string  $uri  Action qui sera exécutée l'API
     * @param  boolean $json Est-ce que la réponse de l'API est au format JSON et doit être décodée ?
     * @return stdClass      Objet JSON
     */
    private function request($uri, $json = true)
    {
        $answer = $this->acurl->http_request(urldecode($this->apiRoot.$uri));

        if($json === true) {
			$answer = json_decode($answer);
        }
		
		//echo $answer;

        $this->checkErrors($answer);

        return $answer;
    }

    /**
     * Renvoie la liste des top 100 torrents
     * @return stdClass Objet JSON
     */
    public function getTop100()
    {
        return $this->request('/torrents/top/100');
    }

    /**
     * Renvoie la liste des torrents du jour
     * @return stdClass Objet JSON
     */
    public function getTopToday()
    {
        return $this->request('/torrents/top/today');
    }

    /**
     * Renvoie la liste des torrents de la semaine
     * @return stdClass Objet JSON
     */
    public function getTopWeek()
    {
        return $this->request('/torrents/top/week');
    }

    /**
     * Renvoie la liste des torrents du mois
     * @return stdClass Objet JSON
     */
    public function getTopMonth()
    {
        return $this->request('/torrents/top/month');
    }

    /**
     * Télécharge le fichier .torrent associé
     *
     * @param  stdClass $torrent Objet contenant diverses informations sur un torrent
     * @return blob Données binaires du fichier
     */
    public function downloadTorrent($torrent)
    {
        return $this->request('/torrents/download/' . $torrent->id, false);
    }
	
	/**
     * Télécharge le fichier .torrent par l'ID
     *
     * @param  stdClass $torrent Objet contenant diverses informations sur un torrent
     * @return blob Données binaires du fichier
     */
    public function downloadById($id)
    {
        return $this->request('/torrents/download/' . $id, false);
    }
	
	/**
     * Recherche sur T411
     *
     * @param  $querry Chaine à rechercher
     * @return stdClass Object json
     */
    public function search($querry)
    {
        return $this->request('/torrents/search/' . $querry, false);
    }

    /**
     * Filtre les torrents sous une ou plusieurs conditions
     *
     * @param  array $torrents   Liste des torrents à filtrer
     * @param  array $conditions Une ou plusieurs conditions
     * @return Generator
     */
    public function filter(array $torrents, array $conditions = [])
    {
        foreach ($torrents as $torrent)
        {
            $cond = true;

            if(!empty($conditions))
            {
                // Test de toutes les conditions
                foreach($conditions as $field => $expression)
                {
                    $cond = $cond && (isset($torrent->$field) && $this->evalCondition($expression, $torrent->$field));
                }
            }

            if($cond) {
                yield $torrent;
            }
        }
    }
	

    /**
     * Evalue une expression en une "comparaison PHP"
     *
     * @param  string $expression     Valeur suivit ou non d'un opérateur
     * @param  string $valueToCompare Valeur à comparer
     * @return boolean
     */
    private function evalCondition($expression, $valueToCompare)
    {
        list($operator, $value) = $this->parseCondition($expression);

        switch ($operator) {
            case '<':   return $valueToCompare < $value;
            case '<=':  return $valueToCompare <= $value;
            case '==':  return $valueToCompare == $value;
            case '===': return $valueToCompare === $value;
            case '>':   return $valueToCompare > $value;
            case '>=':  return $valueToCompare >= $value;
            case '!=':  return $valueToCompare != $value;
            default:    throw new Exception("Opérateur inconnu '" . $operator . "'", 1);
        }
    }

    /**
     * Parse une expression afin de retourner les composants de cette dernière
     *
     * @param  string $expression L'expression à parser
     * @return array  Tableau contenant l'opérateur et la valeur de l'expression
     */
    private function parseCondition($expression)
    {
        $expression = trim($expression);

        preg_match('/^([^ ]+) ?(.+)$/', $expression, $matches);

        // On n'a pas trouvé d'opérateur, alors on estime que c'est le '==' par défaut
        if(empty($matches))
        {
            $operator = '==';
            $value    = $expression;
        }
        else
        {
            $operator = $matches[1];
            $value    = $matches[2];
        }

        // Un petit clean trkl
        $operator = trim($operator);
        $value    = trim($value);

        return [$operator, $value];
    }

    /**
     * Retourne la valeur d'une des propriétés de l'objet, ou null
     *
     * @param  string $key Nom de la propriété
     * @return mixed|null
     */
    private function _get($key)
    {
        if(isset($this->$key))
        {
            return $this->$key;
        }

        return null;
    }

    /**
     * Retourne le nom d'utilisateur du compte
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->_get('username');
    }

    /**
     * Modifie le nom d'utilisateur du compte avec une nouvelle valeur
     *
     * @param string $username Nouveau nom d'utilisateur
     */
    public function setUsername($username)
    {
        $this->username = trim($username);
    }

    /**
     * Retourne le mot de passe associé au compte
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->_get('password');
    }

    /**
     * Modifie le mot de passe associé au compte
     *
     * @param string $password Nouveau mot de passe
     */
    public function setPassword($password)
    {
        $this->password = trim($password);
    }
}
