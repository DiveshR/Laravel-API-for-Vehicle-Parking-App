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

```````
- Api subfolder to specify that it's an API Controller
- V1 subfolder to specify that it's Version 1 of the API 
- Auth subfolder to specify that it's a Controller related to Auth

app/Http/Controllers/Api/V1/Auth/RegisterController.php

```php

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

`````````

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

 ## Lesson-5 - Manage User's Vehicles


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

 --------------------------------------------------------------------------------------------------------------------

 ## Lesson-6 - Get Parking Zones

 Now we need to have a list of the parking zones/areas, to show them in the application and to allow the user to choose where to start parking.

This will be only one public API endpoint: GET /api/v1/zones

So, let's generate a Controller:

```php
php artisan make:controller Api/V1/ZoneController

``````

Also, we generate the API Resource:
````php
php artisan make:resource ZoneResource
```````

app/Http/Resources/ZoneResouce.php:

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_per_hour' => $this->price_per_hour,
        ];
    }
}

`````

app/Http/Controllers/Api/V1/ZoneController.php:

```php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zone;
use App\Http\Resources\ZoneResource;

class ZoneController extends Controller
{
    public function index()
    {
        return ZoneResource::collection(Zone::latest()->get());
    }
}

``````

routes/api.php:

```php
Route::middleware('auth:sanctum')->group( function(){
 ////////
});

Route::get('zones', [ZoneController::class, 'index']);

````

--------------------------------------------------------------------------------------------------------------------

 ## Lesson-7 - Start/Stop Parking

```php

php artisan make:controller Api/V1/ParkingController

````

Also, I will use API Resource here, because we would need to return the Parking data in a few places.

````php

php artisan make:resource ParkingResource

```````

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone' => [
                'name' => $this->zone->name,
                'price_per_hour' => $this->zone->price_per_hour,
            ],
            'vehicle' => [
                'plate_number' => $this->vehicle->plate_number
            ],
            'start_time' => $this->start_time->toDateTimeString(),
            'stop_time' => $this->stop_time?->toDateTimeString(),
            'total_price' => $this->total_price,
        ];
    }
}

```````

Important notice: we're converting the start_time and stop_time fields to date-time strings, and we can do that because of the $casts we defined in the model earlier. Also, the stop_time field has a question mark, because it may be null, so we use the syntax stop_time?->method() to avoid errors about using a method on a null object value.

Now, we need to get back to our Model and define the zone() and vehicle() relations. Also, for convenience, we will add two local scopes that we will use later.


app/Models/Parking.php:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parking extends Model
{
   ///////......

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('stop_time');
    }

    public function scopeStopped($query)
    {
        return $query->whereNotNull('stop_time');
    }
    
}


```

Now, let's try to start the parking.

app/Http/Controllers/Api/V1/ParkingController.php:

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ParkingRequest;
use App\Models\Parking;
use Illuminate\Http\Response;
use App\Http\Resources\ParkingResource;

class ParkingController extends Controller
{
    public function start(ParkingRequest $request)
    {

        if (Parking::active()->where('vehicle_id', $request->vehicle_id)->exists()) {
            return response()->json([
                'errors' => ['general' => ['Can\'t start parking twice using same vehicle. Please stop currently active parking.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parking = Parking::create($request->validated());
        $parking->load('vehicle', 'zone');

        return ParkingResource::make($parking);
    }
}

````

So, we validate the data, check if there are no started parking with the same vehicle, create the Parking object, load its relationships to avoid the N+1 query problem and return the data transformed by API resource.

Next, we create the API endpoint in the routes.

```php

use App\Http\Controllers\Api\V1\ParkingController;
 
// ...
 
Route::middleware('auth:sanctum')->group(function () {
    // ... profile and vehicles
 
    Route::post('parkings/start', [ParkingController::class, 'start']);
});

`````

We will also use the user_id multi-tenancy here, like in the Vehicles?

Not only that, but in this case, we also auto-set the start_time value.

Generate the Observer:

```php
php artisan make:observer ParkingObserver --model=Parking
````

```php
namespace App\Observers;

use App\Models\Parking;

class ParkingObserver
{
    /**
     * Handle the Parking "creating" event.
     */
    public function creating(Parking $parking): void
    {
        if(auth()->check()){
            $parking->user_id = auth()->id();
        }
        
        $parking->start_time = now();
    }


``````

Notice: technically, we could not even create a parkings.user_id column in the database, so we would get the user from their vehicle, but in this way, it would be quicker to get the user's parking without loading the relationship each time.

Then we register the Observer

```php
namespace App\Providers;

use App\Models\Parking;
use App\Models\Vehicle;
use App\Observers\ParkingObserver;
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
        Parking::observe(ParkingObserver::class);
    }
}


`````

Finally, we add a Global Scope to the model.

app/Models/Parking.php:

````php
use Illuminate\Database\Eloquent\Builder;
 
class Parking extends Model
{
    // ...
 
    protected static function booted()
    {
        static::addGlobalScope('user', function (Builder $builder) {
            $builder->where('user_id', auth()->id());
        });
    }
 
}
```````````
Now, call the endpoint.


Next, we need to stop the current parking, right? But first, we need to get the data for it, show it on the screen, and then allow the user to click "Stop".

So we need another endpoint to show() the data.

A new Controller method, reusing the same API resource:

app/Http/Controllers/Api/V1/ParkingController.php:

```php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ParkingRequest;
use App\Models\Parking;
use Illuminate\Http\Response;
use App\Http\Resources\ParkingResource;

class ParkingController extends Controller
{
    public function start(ParkingRequest $request)
    {
        .../////

    }

    public function show(Parking $parking)
    {
        return ParkingResource::make($parking);
    }
}

```

And a new route, using route model binding:

routes/api.php:

```php
    Route::get('parkings/{parking}',[ParkingController::class,'show']);
`````

And now, as we have the ID record of the parking that we need to stop, we can create a special Controller method for it:

````php
public function stop(Parking $parking)
{
    $parking->update([
        'stop_time' => now(),
    ]);
 
    return ParkingResource::make($parking);
}

Route::middleware('auth:sanctum')->group(function () {
    // ...
 
    Route::post('parkings/start', [ParkingController::class, 'start']);
    Route::get('parkings/{parking}', [ParkingController::class, 'show']);
    Route::put('parkings/{parking}', [ParkingController::class, 'stop']);
});

`````

When calling this API endpoint, we don't need to pass any parameters in the body, the record is just updated, successfully.


--------------------------------------------------------------------------------------------------------------------

 ## Lesson-8 - Price Calculation

 Users need to know how much they would pay for parking. It should happen in three phases:
- Before parking - when getting the list of zones.
- During parking - when getting the parking by ID.
- After parking - as a result of the stopping function.

We need to create some function to calculate the current price by zone and duration, and then save that price in the parkings.total_price when the parking is stopped.

For that, let's create a separate Service class with a method to calculate the price. In Laravel, there's no Artisan command make:service

app/Services/ParkingPriceService.php:

```php
use App\Models\Zone;
use Carbon\Carbon;
 
class ParkingPriceService {
 
    public static function calculatePrice(int $zone_id, string $startTime, string $stopTime = null): int
    {
        $start = new Carbon($startTime);
        $stop = (!is_null($stopTime)) ? new Carbon($stopTime) : now();
 
        $totalTimeByMinutes = $stop->diffInMinutes($start);
 
        $priceByMinutes = Zone::find($zone_id)->price_per_hour / 60;
 
        return ceil($totalTimeByMinutes * $priceByMinutes);
    }
 
}

`````
As you can see, we convert $startTime and $stopTime to Carbon objects, calculate the difference, and multiply that by price per minute, for better accuracy than calculating per hour.


Notice: alternatively, you can choose to convert the DB fields to Carbon objects automatically, by using Eloquent casting.

Now, where do we use that service?

First, in the stop() method of the Controller.

```php
use App\Models\Parking;
use App\Services\ParkingPriceService;
 
class ParkingController extends Controller
{
    public function stop(Parking $parking)
    {
        $parking->update([
            'stop_time' => now(),
            'total_price' => ParkingPriceService::calculatePrice($parking->zone_id, $parking->start_time),
        ]);
 
        return ParkingResource::make($parking);
    }
}

`````

Note that this Service with a static method is only one way to do it. You could put this method in the Model itself, or a Service with a non-static regular method.

So, when the parking is stopped, calculations are performed automatically, and in the DB, we have the saved value:

Laravel API Prices

But what if the user wants to find the current price before the parking is stopped? Well, we can call the calculation directly on the API Resource file:

app/Http/Resources/ParkingResource.php:

```php

use App\Services\ParkingPriceService;
 
class ParkingResource extends JsonResource
{
    public function toArray($request)
    {
        $totalPrice = $this->total_price ?? ParkingPriceService::calculatePrice(
            $this->zone_id,
            $this->start_time,
            $this->stop_time
        );
 
        return [
            'id' => $this->id,
            'zone' => [
                'name' => $this->zone->name,
                'price_per_hour' => $this->zone->price_per_hour,
            ],
            'vehicle' => [
                'plate_number' => $this->vehicle->plate_number
            ],
            'start_time' => $this->start_time,
            'stop_time' => $this->stop_time,
            'total_price' => $totalPrice,
        ];
    }
}

````

So, if we don't have a stop_time yet, the current price will be calculated in real-time mode:

Laravel API Prices

So, that's about it: we've created all the basic API functionality for the parking application.
