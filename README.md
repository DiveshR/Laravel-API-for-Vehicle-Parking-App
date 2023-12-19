# Build Laravel API for Vehicle Parking App: Step-By-Step

We will create a real-life API for a vehicle parking application.

 - DB structure
 - User registration and login
 - Profile and password management
 - Non-public endpoints Auth protection by Laravel Sanctum
 - Managing vehicles and parking start/stop events
 - Laravel API Postman
 - Automated PHPUnit tests and generate the documentation for our API

 ## Lesson-1 - DB Schema and Functionality Plan

 First, what we're creating here?

 Imagine a mobile app to park the vehicle somewhere in a big city, to start/stop the parking.

Features:

 - User register
 - User login
 - User view/update profile/password
 - Manage user's vehicles
 - Get prices for parking zones/areas
 - Start/stop parking at a chosen zone
 - View the current/total price of parking

### These are the main DB models, from them we can calculate everything and make any report we want.

- Users
- Cars/vehicles
- Parking zones/areas with prices
- Parking events: start/stop

```php
php artisan make:model Vehicle -m
```
```php
            Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('plate_number');
            $table->timestamps();
            $table->softDeletes();
        });

```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'plate_number'];
}
````

Next, the parking zone/area entity. Let's just call it "zone"

```php
php artisan make:model Zone -m
```
```php
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('price_per_hour');
            $table->timestamps();
        });

        Zone::create(['name' => 'Green Zone', 'price_per_hour' => 100]);
        Zone::create(['name' => 'Yellow Zone', 'price_per_hour' => 200]);
        Zone::create(['name' => 'Red Zone', 'price_per_hour' => 300]);
```

You can use the Eloquent model immediately here in the migrations? Another option is to generate a Seeder class and put the data there

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'price_per_hour'];
}
````

Finally, when we start/stop parking, we will deal with the DB table "parkings".

```php
php artisan make:model Parking -m
```
```php
    public function up(): void
    {
        Schema::create('parkings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('zone_id')->constrained();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('stop_time')->nullable();
            $table->integer('total_price')->nullable();
            $table->timestamps();
        });
    }
```

As you can see: three foreign key fields, start/stop time, and total_price at the end of the parking event.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parking extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'vehicle_id', 'zone_id', 'start_time', 'stop_time', 'total_price'];

    protected $casts = [
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
    ];
    
}
`````

Also, we add the $casts to the datetime columns, to be able to easily convert them later to whichever format we want.

Later, we will add relationship methods in the models

```php
php artisan migrate
```

--------------------------------------------------------------------------------------------------------------------

 ## Lesson-2 - User Area: Register New User

 let's create our first API endpoint. To manage their parking and vehicles, of course, we need to have users. Their DB structure and model come by default with Laravel, we "just" need to create the endpoints for them to register and then log in.

We will use Laravel Sanctum for the authentication, with its generated tokens.

Let's create the first endpoint: GET /api/v1/auth/register.

Single Action Controllers

If a controller action is particularly complex, you might find it convenient to dedicate an entire controller class to that single action.

```php
php artisan make:controller Api/V1/Auth/RegisterController --invokable
````
- Api subfolder to specify that it's an API Controller
- V1 subfolder to specify that it's Version 1 of the API 
- Auth subfolder to specify that it's a Controller related to Auth

app/Http/Controllers/Api/V1/Auth/RegisterController.php

````php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        //
    }
}
```

Next, let's create a Route for this Controller.
routes/api.php:

```php
use App\Http\Controllers\Api\V1\Auth\RegisterController;

Route::post('auth/register', RegisterController::class);

```
Here, we don't need to specify the method name. We can reference a full Controller class, because it's a Single Action "invokable" controller, as I already mentioned.

Now, versioning. Where does that /api/v1 comes from automatically? Why don't we specify it in the Routes file? Generally, I recommend always versioning your APIs: even if you're not planning future versions, for now, it's a good practice to start with "v1".

To "attach" your routes/api.php file to the automatic prefix of /api/v1, you need to change the logic in the app/Providers/RouteServiceProvider.php file:


```php

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
            // CHANGE HERE FROM 'api' to 'api/v1'
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
```

Now  Creating Form Requests for validation:
```php
php artisan make:request Api/V1/RegisterRequest
```

app/Requests/Api/V1/RegisterRequest.php

```php
namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
``````

 - name
 - email
- password
 - password_confirmation (the validation rule "confirmed")

```php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Response;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        event(new Registered($user));
        $device = substr($request->userAgent() ?? '',0,255);

        return response()->json([
            'access_token' => $user->createToken($device)->plainTextToken,
        ], Response::HTTP_CREATED);
    }
}

```

In case of validation errors, if you provide "Accept: application/json" in the header, the result will be returned like this, with the HTTP status code 422.

Also, as you can see, we have a $device variable, coming automatically from the User Agent, so we're creating a token specifically for that front-end device, like a mobile phone.

Have you noticed the Response::HTTP_CREATED?

They come from Symfony, and the most widely used one are these:

```php
const HTTP_OK = 200;
const HTTP_CREATED = 201;
const HTTP_ACCEPTED = 202;
const HTTP_NO_CONTENT = 204;
const HTTP_MOVED_PERMANENTLY = 301;
const HTTP_FOUND = 302;
const HTTP_BAD_REQUEST = 400;
const HTTP_UNAUTHORIZED = 401;
const HTTP_FORBIDDEN = 403;
const HTTP_NOT_FOUND = 404;
const HTTP_METHOD_NOT_ALLOWED = 405;
const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
const HTTP_UNPROCESSABLE_ENTITY = 422;

````

Finally, we're firing a general Laravel Auth event, that could be caught with any Listeners in the future - this is done by Laravel Breeze and other starter kits by default


--------------------------------------------------------------------------------------------------------------------

 ## Lesson-3 - User Area: User Login

 Let's generate a Controller for the login mechanism:

 ```php
 php artisan make:controller Api/V1/Auth/LoginController
 ``````

```php
php artisan make:controller Api/V1/Auth/LoginController --invokable
````````

In the routes/api.php file:

```php
Route::post('auth/login', Auth\LoginController::class);
````````

Now  Creating Form Requests for Login validation:
```php
php artisan make:request Api/V1/LoginRequest
```

````php
namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }
}

`````

````php
namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $device = substr($request->userAgent() ?? '', 0, 255);
        $expiresAt = $request->remember ? null : now()->addMinutes(config('session.lifetime'));

                return response()->json([
            'access_token' => $user->createToken($device, expiresAt: $expiresAt)->plainTextToken,
        ], Response::HTTP_CREATED);
    }
}
````

We implement the "remember me" functionality: if the $request->remember is present and true, then we set the additional expiresAt parameter in the Sanctum createToken() method.


--------------------------------------------------------------------------------------------------------------------

 ## Lesson-4 - User Area: Get/Update Profile

 - GET /profile - to view profile details
 - PUT /profile - to update name/email
-  PUT /password - to update the password

Get/Update Profile
Let's generate a Profile Controller - this time with two methods in it.

```php
php artisan make:controller Api/V1/Auth/ProfileController

```

app/Http/Controllers/Api/V1/Auth/ProfileController.php:

```php

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;

class ProfileController extends Controller
{
        public function show(Request $request)
    {
        return response()->json($request->user()->only('name', 'email'));
    }

        public function update(ProfileUpdateRequest $request)
    {
        $validatedData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        auth()->user()->update($validatedData);
 
        return response()->json($validatedData, Response::HTTP_ACCEPTED);
    }
}
````

Not sure I need to explain much here: in the show() method we just show a few fields of a logged-in user (we don't show any ID or password-sensitive fields), and in the update() method we validate the data, update the DB row and return the updated data as JSON.

Now, the most important part: how do we get that auth()->user() or $request->user() automatically?

In the routes, we will make those endpoints as a group, with the Middleware auth:sanctum. Then, we pass a Bearer token in the API request. Yes, the one that we got returned from login/register.

routes/api.php:

```php
Route::middleware('auth:sanctum')->group( function(){
    Route::get('profile', [ProfileController::class,'show']);
    Route::put('profile', [ProfileController::class, 'update']);
});

````
Now, if we try to make a request without any token, we should get a 401 status code, which means unauthenticated.


Now, let's create the Change Password endpoint.

Change Password

```php
php artisan make:controller Api/V1/Auth/PasswordUpdateController --invokable

````

app/Http/Controllers/Api/V1/Auth/PasswordUpdateController.php:

```php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use App\Http\Requests\Api\V1\PasswordUpdateRequest;

class PasswordUpdateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PasswordUpdateRequest $request)
    {
                auth()->user()->update([
            'password' => bcrypt($request->input('password')),
        ]);

                return response()->json([
            'message' => 'Your password has been updated.',
        ], Response::HTTP_ACCEPTED);
    }
}

`````

Now, we add it to the routes.

routes/api.php:

```php

Route::middleware('auth:sanctum')->group( function(){
    Route::get('profile', [ProfileController::class,'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('password', PasswordUpdateController::class);
});

````

Logout

Now we know hot to make the requests for the logged-in user, passing a Bearer Token.

If we want the user to log out of the API access, all we need to do is destroy the token that Sanctum had generated at the login/register.

```php
php artisan make:controller Api/V1/Auth/LogoutController --invokable

````

And a new endpoint inside the same group with auth:sanctum Middleware:

routes/api.php:

```php
Route::post('auth/logout', Auth\LogoutController::class);
``````

app/Http/Controllers/Api/V1/Auth/LogoutController.php:

```php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}


`````


We can access the currently logged-in user with $request->user() or auth()->user(), they are identical. And then, we call the Sanctum's methods of currentAccessToken() and then delete(), returning the noContent() which shows 204 HTTP Status Code.


--------------------------------------------------------------------------------------------------------------------

 ## Lesson-4 - Manage User's Vehicles


app/Models/Vehicle.php:

So now we need API endpoints for a user to manage their vehicles. This should be a typical CRUD, with these 5 methods in the Controller:

- index
- store
- show
- update
- delete

````php
php artisan make:controller Api/V1/VehicleController --resource --api --model=Vehicle

``````
Also, before filling in the Controler code, let's generate the API Resource that would represent our Vehicle:

```php
php artisan make:resource VehicleResource

````

For our API, we don't need to return the user_id and timestamp fields, so we will shorten it to this:

```php
app/Http/Resources/VehicleResource.php:

class VehicleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
        ];
    }
}

````

Now, generate is the Form Request class for the validation:

```php
php artisan make:request Api/V1/StoreVehicleRequest
```
app/Http/Requests/Api/V1/StoreVehicleRequest.php:


```php
namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plate_number' => 'required'
        ];
    }
}


````

Now, we finally get back to our Controller and fill it in, using the API Resource and Form Request from above:

app/Http/Controllers/Api/V1/VehicleController.php:

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\Response;
use App\Http\Resources\VehicleResource;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return VehicleResource::collection(Vehicle::latest());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {
        $vehicle = Vehicle::create($request->validated());

        return VehicleResource::make($vehicle);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        return VehicleResource::make($vehicle);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreVehicleRequest $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validated());

        return response()->json(VehicleResource::make($vehicle), Response::HTTP_ACCEPTED);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return response()->noContent();
    }
}

``````

- We use the VehicleResource in a few places - once to return a collection and three times for a single model
- We use $request->validated() because this is returned from the Form Request class
- We reuse the same StoreVehicleRequest in this case because validation rules are identical for store and update
- We don't return anything from the destroy() method because, well, there's nothing to return if there's no vehicle anymore, right?

routes/api.php:

```php
Route::apiResource('vehicles', VehicleController::class);

`````

Automatically, the Route::apiResource() will generate 5 API endpoints:

- GET /api/v1/vehicles
- POST /api/v1/vehicles
- GET /api/v1/vehicles/{vehicles.id}
- PUT /api/v1/vehicles/{vehicles.id}
- DELETE /api/v1/vehicles/{vehicles.id}


What about user_id field?

And you're right, it's nowhere to be seen in the Controller.

What we'll do now can be called a "multi-tenancy" in its simple form. Essentially, every user should see only their vehicles. So we need to do two things:

- Automatically set vehicles.user_id for new records with auth()->id();
- Filter all DB queries for the Vehicle model with ->where('user_id', auth()->id()).

The first one can be performed in a Model Observer:

```php
php artisan make:observer VehicleObserver --model=Vehicle

`````

Then we fill in the creating() method. Important notice: it's creating(), not created().

app/Observers/VehicleObserver.php:


```php
namespace App\Observers;

use App\Models\Vehicle;

class VehicleObserver
{
    /**
     * Handle the Vehicle "creating" event.
     */
    public function creating(Vehicle $vehicle)
    {
        if (auth()->check()) {
            $vehicle->user_id = auth()->id();
        }
    }

```````

Then, we register our Observer.

app/Providers/AppServiceProvider.php:

````php

namespace App\Providers;

use App\Models\Vehicle;
use App\Observers\VehicleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vehicle::observe(VehicleObserver::class);
    }
}

``````


And now, we can try to POST a new vehicle! Remember, we still need to pass the same Auth Bearer token, as in the last examples! That will determine the auth()->id() value for the Observer and any other parts of the code.

Now, we need to filter out the data while getting the Vehicles. For that, we will set up a Global Scope in Eloquent. It will help us to avoid the ->where() statement every we would need it. Specifically, we will use the Anonymous Global Scope syntax and add this code to our Vehicle model:

app/Models/Vehicle.php:

````php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'plate_number'];

    public static function booted()
    {
        static::addGlobalScope('user', function (Builder $builder) {
            $builder->where('user_id', auth()->id());
        });
    }
}

```````````

Laravel API Vehicles

But if we try to get the Vehicle list with the Bearer Token defining our user, we get only our own Vehicle:

Not only that, if we try to get someone else's Vehicle by guessing its ID, we will get a 404 Not Found response:
# Laravel-API-for-Vehicle-Parking-App