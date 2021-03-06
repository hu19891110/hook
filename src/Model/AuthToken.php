<?php namespace Hook\Model;

use Hook\Application\Config;

use Hook\Http\Request;
use Hook\Http\Input;

use \Carbon\Carbon;

/**
 * AuthToken
 */
class AuthToken extends Model
{
    const DEFAULT_TOKEN_EXPIRATION = 24; // in hours

    protected static $_current = null;

    public $timestamps = false;

    protected $dates = array('expire_at');
    protected $hidden = array('auth');

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) { $model->beforeCreate(); });
    }

    /**
     * current - get current active AuthToken instance
     * @static
     * @return AuthToken|null
     */
    public static function current()
    {
        $token = Request::header('X-Auth-Token', Input::get('X-Auth-Token'));

        if (static::$_current === null && $token) {
            static::$_current = static::where('token', $token)
                ->where('expire_at', '>=', Carbon::now())
                ->first();
        }

        return static::$_current;
    }

    public static function setCurrent($auth_token) {
        static::$_current = $auth_token;
    }

    public function auth()
    {
        return $this->belongsTo('Hook\Model\Auth');
    }

    /**
     * isExpired
     * @return bool
     */
    public function isExpired()
    {
        return Carbon::now() > $this->expire_at;
    }

    public function beforeCreate()
    {
        // cache Auth role for this token
        //
        // TODO: use auth() relationship.
        // Due the same problem at Auth::current(), it was needed to use
        // App::collection here
        //
        $this->role = App::collection('auth')->where('_id', $this->auth_id)->first()->role;
        $this->created_at = Carbon::now();

        $token_expiration = Config::get('auth.token_expiration', static::DEFAULT_TOKEN_EXPIRATION);
        $this->expire_at = Carbon::now()->addHours($token_expiration);
        $this->token = sha1(uniqid(rand(), true));
    }

}
