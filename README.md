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



--------------------------------------------------------------------------------------------------------------------

 ## Lesson-9 - Generate API Docs

- Endpoints
- Parameters
- Auth mechanism
- Example responses


Laravel packages that allow you to generate such docs automatically.

Package for this is called Scribe(https://scribe.knuckles.wtf/),

First, we install it:

```php

composer require --dev knuckleswtf/scribe

````


Then we publish the config. In the case of this package, it's really important, because you will definitely want to change some config values:

```php

php artisan vendor:publish --tag=scribe-config

```

config/scribe.php:

Disable automatic responses

````php

return [
    // The HTML <title> for the generated documentation. If this is empty, Scribe will infer it from config('app.name').
    'title' => null,

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => '',

    // The base URL displayed in the docs. If this is empty, Scribe will use the value of config('app.url') at generation time.
    // If you're using `laravel` type, you can set this to a dynamic string, like '{{ config("app.tenant_url") }}' to get a dynamic base URL.
    'base_url' => null,

    'routes' => [
        [
            // Routes that match these conditions will be included in the docs
            'match' => [
                // Match only routes whose paths match this pattern (use * as a wildcard to match any characters). Example: 'users/*'.
                'prefixes' => ['api/*'],

                // Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                'domains' => ['*'],

                // [Dingo router only] Match only routes registered under this version. Wildcards are NOT supported.
                'versions' => ['v1'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            // Exclude these routes even if they matched the rules above.
            'exclude' => [
                // 'GET /health', 'admin.*'
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but generate a basic template,
    // passing the OpenAPI spec as a URL, allowing you to easily use the docs with an external generator
    'type' => 'static',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        // Whether to automatically create a docs endpoint for you to view your generated docs.
        // If this is false, you can still set up routing manually.
        'add_routes' => true,

        // URL path to use for the docs endpoint (if `add_routes` is true).
        // By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets.
        // By default, assets are stored in `public/vendor/scribe`.
        // If set, assets will be stored in `public/{{assets_directory}}`
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint (if `add_routes` is true).
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => []
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL for the API tester to use (for example, you can set this to your staging URL).
        // Leave as null to use the current app URL when generating (config("app.url")).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication.
        'enabled' => false,

        // Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
        // You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
        'default' => false,

        // Where is the auth value meant to be sent in a request?
        // Options: query, body, basic, bearer, header (for custom header)
        'in' => 'bearer',

        // The name of the auth parameter (eg token, key, apiKey) or header (eg Authorization, Api-Key).
        'name' => 'key',

        // The value of the parameter to be used by Scribe to authenticate response calls.
        // This will NOT be included in the generated documentation. If empty, Scribe will use a random value.
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder your users will see for the auth parameter in the example requests.
        // Set this to null if you want Scribe to use a random value as placeholder instead.
        'placeholder' => '{YOUR_AUTH_KEY}',

        // Any extra authentication-related info for your users. Markdown and HTML are supported.
        'extra_info' => 'You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.',
    ],

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    'intro_text' => <<<INTRO
This documentation aims to provide all the information you need to work with our API.

<aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>
INTRO
    ,

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    'example_languages' => [
        'bash',
        'javascript',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec (v3.0.1) in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group.
        'default' => 'Endpoints',

        // By default, Scribe will sort groups alphabetically, and endpoints in the order their routes are defined.
        // You can override this by listing the groups, subgroups and endpoints here in the order you want them.
        // See https://scribe.knuckles.wtf/blog/laravel-v4#easier-sorting and https://scribe.knuckles.wtf/laravel/reference/config#order for details
        'order' => [],
    ],

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Set this to any number (eg. 1234) to generate the same example values for parameters on each run,
        'faker_seed' => null,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // By default, Scribe will try the model's factory, and if that fails, try fetching the first from the database.
        // You can reorder or remove strategies here.
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // If you create or install a custom strategy, add it here.
    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromDocBlocks::class,
            Strategies\Metadata\GetFromMetadataAttributes::class,
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromUrlParamAttribute::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],
        'queryParameters' => [
            Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamAttribute::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromHeaderAttribute::class,
            Strategies\Headers\GetFromHeaderTag::class,
            [
                'override',
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]
        ],
        'bodyParameters' => [
            Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamAttribute::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            Strategies\Responses\UseResponseAttributes::class,
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
            [
                Strategies\Responses\ResponseCalls::class,
                ['only' => ['GET *']]
            ]
        ],
        'responseFields' => [
            Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Tell Scribe which connections should be transacted here. If you only use one db connection, you can leave this as is.
    'database_connections_to_transact' => [config('database.default')],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],

    'routeMatcher' => \Knuckles\Scribe\Matching\RouteMatcher::class,
];



`````

Provide API Auth Information
One of the first things our API consumers would see is how to authenticate. Let's change a few default values in the config, to specify that we are expecting a "Bearer Token":

'auth' => [
    'enabled' => true, // previous value: false
    'name' => 'token', // previous value: 'key'
    'placeholder' => '{TOKEN}', // previous value: '{YOUR_AUTH_KEY}'

Group Endpoints
Now, we get to our Controllers and we need to define the groups in the docs that will become parent menu items.


```php
app/Http/Controllers/Api/V1/Auth/LoginController.php:

/**
 * @group Auth
 */
class LoginController extends Controller // ...

app/Http/Controllers/Api/V1/Auth/RegisterController.php:

/**
 * @group Auth
 */
class RegisterController extends Controller

app/Http/Controllers/Api/V1/Auth/ProfileController.php:

/**
 * @group Auth
 */
class ProfileController extends Controller

app/Http/Controllers/Api/V1/Auth/PasswordUpdateController.php:

/**
 * @group Auth
 */
class PasswordUpdateController extends Controller

app/Http/Controllers/Api/V1/VehicleController.php:

/**
 * @group Vehicles
 */
class VehicleController extends Controller

app/Http/Controllers/Api/V1/ZoneController.php:

/**
 * @group Zones
 */
class ZoneController extends Controller

app/Http/Controllers/Api/V1/ParkingController.php:

/**
 * @group Parking
 */
class ParkingController extends Controller

````

And now, finally, we can generate the docs:

```php
php artisan scribe:generate

php artisan serve

http://127.0.0.1:8000/docs

````