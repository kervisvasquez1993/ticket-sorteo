<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateServicePattern extends Command
{
    protected $signature = 'make:service-pattern {name : The name of the service} {--api : Create as API controller}';
    protected $description = 'Create a service pattern structure including interface, service, repository, DTO, controller and requests';

    public function handle()
    {
        $name = $this->argument('name');
        $isApi = $this->option('api');

        $this->info("Creating service pattern for {$name}...");

        // Create directories if they don't exist
        $this->createDirectories($name);

        // Create files
        $this->createInterfaces($name);
        $this->createService($name);
        $this->createRepository($name);
        $this->createDTO($name);
        $this->createController($name, $isApi);
        $this->createRequests($name);

        // Update Provider
        $this->updateServiceProvider($name);

        $this->info("Service pattern for {$name} created successfully!");
        $this->info("Don't forget to register routes for your new controller.");

        return Command::SUCCESS;
    }

    private function createDirectories($name)
    {
        $paths = [
            app_path("Interfaces/{$name}"),
            app_path("Services/{$name}"),
            app_path("Repository/{$name}"),
            app_path("DTOs/{$name}"),
            app_path("Http/Requests/{$name}"),
        ];

        foreach ($paths as $path) {
            if (!File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
                $this->info("Directory created: {$path}");
            }
        }
    }

    private function createInterfaces($name)
    {
        $servicePath = app_path("Interfaces/{$name}/I{$name}Services.php");
        $repositoryPath = app_path("Interfaces/{$name}/I{$name}Repository.php");

        // Service Interface
        $serviceContent = "<?php\n\nnamespace App\\Interfaces\\{$name};\n\nuse App\\DTOs\\{$name}\\DTOs{$name};\n\ninterface I{$name}Services \n{\n    public function getAll{$name}s();\n    public function get{$name}ById(\$id);\n    public function create{$name}(DTOs{$name} \$data);\n    public function update{$name}(DTOs{$name} \$data, \$id);\n    public function delete{$name}(\$id);\n}\n";

        // Repository Interface
        $repositoryContent = "<?php\n\nnamespace App\\Interfaces\\{$name};\n\nuse App\\DTOs\\{$name}\\DTOs{$name};\nuse App\\Models\\{$name};\n\ninterface I{$name}Repository \n{\n    public function getAll{$name}s();\n    public function get{$name}ById(\$id): {$name};\n    public function create{$name}(DTOs{$name} \$data): {$name};\n    public function update{$name}(DTOs{$name} \$data, {$name} \${$name}): {$name};\n    public function delete{$name}({$name} \${$name}): {$name};\n}\n";

        File::put($servicePath, $serviceContent);
        File::put($repositoryPath, $repositoryContent);

        $this->info("Created interface files");
    }

    private function createService($name)
    {
        $path = app_path("Services/{$name}/{$name}Services.php");

        // CORECCIÓN: Usar interface en lugar de clase concreta
        $content = "<?php\n\nnamespace App\\Services\\{$name};\n\nuse App\\DTOs\\{$name}\\DTOs{$name};\nuse App\\Interfaces\\{$name}\\I{$name}Services;\nuse App\\Interfaces\\{$name}\\I{$name}Repository;\nuse Exception;\n\nclass {$name}Services implements I{$name}Services \n{\n    protected I{$name}Repository \${$name}Repository;\n    \n    public function __construct(I{$name}Repository \${$name}RepositoryInterface)\n    {\n        \$this->{$name}Repository = \${$name}RepositoryInterface;\n    }\n    \n    public function getAll{$name}s()\n    {\n        try {\n            \$results = \$this->{$name}Repository->getAll{$name}s();\n            return [\n                'success' => true,\n                'data' => \$results\n            ];\n        } catch (Exception \$exception) {\n            return [\n                'success' => false,\n                'message' => \$exception->getMessage()\n            ];\n        }\n    }\n    \n    public function get{$name}ById(\$id)\n    {\n        try {\n            \$results = \$this->{$name}Repository->get{$name}ById(\$id);\n            return [\n                'success' => true,\n                'data' => \$results\n            ];\n        } catch (Exception \$exception) {\n            return [\n                'success' => false,\n                'message' => \$exception->getMessage()\n            ];\n        }\n    }\n    \n    public function create{$name}(DTOs{$name} \$data)\n    {\n        try {\n            \$results = \$this->{$name}Repository->create{$name}(\$data);\n            return [\n                'success' => true,\n                'data' => \$results\n            ];\n        } catch (Exception \$exception) {\n            return [\n                'success' => false,\n                'message' => \$exception->getMessage()\n            ];\n        }\n    }\n    \n    public function update{$name}(DTOs{$name} \$data, \$id)\n    {\n        try {\n            \${$name} = \$this->{$name}Repository->get{$name}ById(\$id);\n            \$results = \$this->{$name}Repository->update{$name}(\$data, \${$name});\n            return [\n                'success' => true,\n                'data' => \$results\n            ];\n        } catch (Exception \$exception) {\n            return [\n                'success' => false,\n                'message' => \$exception->getMessage()\n            ];\n        }\n    }\n    \n    public function delete{$name}(\$id)\n    {\n        try {\n            \${$name} = \$this->{$name}Repository->get{$name}ById(\$id);\n            \$results = \$this->{$name}Repository->delete{$name}(\${$name});\n            return [\n                'success' => true,\n                'data' => \$results\n            ];\n        } catch (Exception \$exception) {\n            return [\n                'success' => false,\n                'message' => \$exception->getMessage()\n            ];\n        }\n    }\n}\n";

        File::put($path, $content);
        $this->info("Created service file");
    }

    private function createRepository($name)
    {
        $path = app_path("Repository/{$name}/{$name}Repository.php");

        $content = "<?php\n\nnamespace App\\Repository\\{$name};\n\nuse App\\DTOs\\{$name}\\DTOs{$name};\nuse App\\Interfaces\\{$name}\\I{$name}Repository;\nuse App\\Models\\{$name};\n\nclass {$name}Repository implements I{$name}Repository \n{\n    public function getAll{$name}s()\n    {\n        \${$name}s = {$name}::all();\n        return \${$name}s;\n    }\n    \n    public function get{$name}ById(\$id): {$name}\n    {\n        \${$name} = {$name}::where('id', \$id)->first();\n        if (!\${$name}) {\n            throw new \\Exception(\"No results found for {$name} with ID {\$id}\");\n        }\n        return \${$name};\n    }\n    \n    public function create{$name}(DTOs{$name} \$data): {$name}\n    {\n        \$result = {$name}::create(\$data->toArray());\n        return \$result;\n    }\n    \n    public function update{$name}(DTOs{$name} \$data, {$name} \${$name}): {$name}\n    {\n        \${$name}->update(\$data->toArray());\n        return \${$name};\n    }\n    \n    public function delete{$name}({$name} \${$name}): {$name}\n    {\n        \${$name}->delete();\n        return \${$name};\n    }\n}\n";

        File::put($path, $content);
        $this->info("Created repository file");
    }

    private function createDTO($name)
    {
        $path = app_path("DTOs/{$name}/DTOs{$name}.php");

        $content = "<?php\n\nnamespace App\\DTOs\\{$name};\n\nuse App\\Http\\Requests\\{$name}\\Create{$name}Request;\nuse App\\Http\\Requests\\{$name}\\Update{$name}Request;\nuse Illuminate\\Support\\Facades\\Auth;\n\nclass DTOs{$name} \n{\n    public function __construct(\n        // Define your properties here\n        // private readonly string \$property1,\n        // private readonly string \$property2,\n    ) {}\n    \n    public static function fromRequest(Create{$name}Request \$request): self\n    {\n        \$validated = \$request->validated();\n        \n        return new self(\n            // property1: \$validated['property1'],\n            // property2: \$validated['property2'],\n        );\n    }\n    \n    public static function fromUpdateRequest(Update{$name}Request \$request): self\n    {\n        \$validated = \$request->validated();\n        \n        return new self(\n            // property1: \$validated['property1'],\n            // property2: \$validated['property2'],\n        );\n    }\n    \n    public function toArray(): array\n    {\n        return [\n            // 'property1' => \$this->property1,\n            // 'property2' => \$this->property2,\n        ];\n    }\n    \n    // Add getter methods for each property\n    // public function getProperty1(): string\n    // {\n    //     return \$this->property1;\n    // }\n}\n";

        File::put($path, $content);
        $this->info("Created DTO file");
    }

    private function createController($name, $isApi)
    {
        $namespace = $isApi ? "App\\Http\\Controllers\\Api\\{$name}" : "App\\Http\\Controllers\\{$name}";
        $controllerPath = $isApi ? app_path("Http/Controllers/Api/{$name}") : app_path("Http/Controllers/{$name}");

        if (!File::isDirectory($controllerPath)) {
            File::makeDirectory($controllerPath, 0755, true);
        }

        $path = $controllerPath . "/{$name}Controller.php";

        // CORECCIÓN: Usar interface en lugar de clase concreta
        $content = "<?php\n\nnamespace {$namespace};\n\nuse App\\DTOs\\{$name}\\DTOs{$name};\nuse App\\Http\\Controllers\\Controller;\nuse App\\Http\\Requests\\{$name}\\Create{$name}Request;\nuse App\\Http\\Requests\\{$name}\\Update{$name}Request;\nuse App\\Interfaces\\{$name}\\I{$name}Services;\nuse Illuminate\\Http\\Request;\n\nclass {$name}Controller extends Controller \n{\n    protected I{$name}Services \${$name}Services;\n    \n    public function __construct(I{$name}Services \${$name}ServicesInterface)\n    {\n        \$this->{$name}Services = \${$name}ServicesInterface;\n    }\n    \n    /**\n     * Display a listing of the resource.\n     */\n    public function index()\n    {\n        \$result = \$this->{$name}Services->getAll{$name}s();\n        if (!\$result['success']) {\n            return response()->json([\n                'error' => \$result['message']\n            ], 422);\n        }\n        return response()->json(\$result['data'], 200);\n    }\n    \n    /**\n     * Store a newly created resource in storage.\n     */\n    public function store(Create{$name}Request \$request)\n    {\n        \$result = \$this->{$name}Services->create{$name}(DTOs{$name}::fromRequest(\$request));\n        if (!\$result['success']) {\n            return response()->json([\n                'error' => \$result['message']\n            ], 422);\n        }\n        return response()->json(\$result['data'], 201);\n    }\n    \n    /**\n     * Display the specified resource.\n     */\n    public function show(string \$id)\n    {\n        \$result = \$this->{$name}Services->get{$name}ById(\$id);\n        if (!\$result['success']) {\n            return response()->json([\n                'error' => \$result['message']\n            ], 422);\n        }\n        return response()->json(\$result['data'], 200);\n    }\n    \n    /**\n     * Update the specified resource in storage.\n     */\n    public function update(Update{$name}Request \$request, string \$id)\n    {\n        \$result = \$this->{$name}Services->update{$name}(DTOs{$name}::fromUpdateRequest(\$request), \$id);\n        if (!\$result['success']) {\n            return response()->json([\n                'error' => \$result['message']\n            ], 422);\n        }\n        return response()->json(\$result['data'], 200);\n    }\n    \n    /**\n     * Remove the specified resource from storage.\n     */\n    public function destroy(string \$id)\n    {\n        \$result = \$this->{$name}Services->delete{$name}(\$id);\n        if (!\$result['success']) {\n            return response()->json([\n                'error' => \$result['message']\n            ], 422);\n        }\n        return response()->json(\$result['data'], 200);\n    }\n}\n";

        File::put($path, $content);
        $this->info("Created controller file");
    }

    private function createRequests($name)
    {
        $requestsDir = app_path("Http/Requests/{$name}");

        // Create Request
        $createRequestPath = $requestsDir . "/Create{$name}Request.php";
        $createRequestContent = "<?php\n\nnamespace App\\Http\\Requests\\{$name};\n\nuse Illuminate\\Contracts\\Validation\\Validator;\nuse Illuminate\\Foundation\\Http\\FormRequest;\nuse Illuminate\\Http\\Exceptions\\HttpResponseException;\nuse Illuminate\\Support\\Facades\\Auth;\n\nclass Create{$name}Request extends FormRequest \n{\n    /**\n     * Determine if the user is authorized to make this request.\n     */\n    public function authorize(): bool\n    {\n        return Auth::check(); // Modify this according to your authorization logic\n    }\n    \n    /**\n     * Get the validation rules that apply to the request.\n     *\n     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>\n     */\n    public function rules(): array\n    {\n        return [\n            // Define your validation rules here\n            // 'field' => 'required|string|max:255',\n        ];\n    }\n    \n    /**\n     * Handle a failed validation attempt.\n     */\n    public function failedValidation(Validator \$validator)\n    {\n        throw new HttpResponseException(response()->json(\n            [\n                'message' => 'Validation errors',\n                'data' => \$validator->errors()\n            ],\n            422\n        ));\n    }\n    \n    /**\n     * Handle a failed authorization attempt.\n     */\n    protected function failedAuthorization()\n    {\n        throw new HttpResponseException(response()->json([\n            'message' => 'You are not authorized to perform this action.',\n        ], 403));\n    }\n}\n";

        // Update Request
        $updateRequestPath = $requestsDir . "/Update{$name}Request.php";
        $updateRequestContent = "<?php\n\nnamespace App\\Http\\Requests\\{$name};\n\nuse Illuminate\\Contracts\\Validation\\Validator;\nuse Illuminate\\Foundation\\Http\\FormRequest;\nuse Illuminate\\Http\\Exceptions\\HttpResponseException;\nuse Illuminate\\Support\\Facades\\Auth;\n\nclass Update{$name}Request extends FormRequest \n{\n    /**\n     * Determine if the user is authorized to make this request.\n     */\n    public function authorize(): bool\n    {\n        return Auth::check(); // Modify this according to your authorization logic\n    }\n    \n    /**\n     * Get the validation rules that apply to the request.\n     *\n     * @return array<string, \\Illuminate\\Contracts\\Validation\\ValidationRule|array<mixed>|string>\n     */\n    public function rules(): array\n    {\n        return [\n            // Define your validation rules here\n            // 'field' => 'required|string|max:255',\n        ];\n    }\n    \n    /**\n     * Handle a failed validation attempt.\n     */\n    public function failedValidation(Validator \$validator)\n    {\n        throw new HttpResponseException(response()->json(\n            [\n                'message' => 'Validation errors',\n                'data' => \$validator->errors()\n            ],\n            422\n        ));\n    }\n    \n    /**\n     * Handle a failed authorization attempt.\n     */\n    protected function failedAuthorization()\n    {\n        throw new HttpResponseException(response()->json([\n            'message' => 'You are not authorized to perform this action.',\n        ], 403));\n    }\n}\n";

        File::put($createRequestPath, $createRequestContent);
        File::put($updateRequestPath, $updateRequestContent);

        $this->info("Created request files");
    }

    private function updateServiceProvider($name)
    {
        $providerPath = app_path('Providers/RepositoriesServicesProvider.php');

        if (!File::exists($providerPath)) {
            $this->error("Provider file not found: {$providerPath}");
            return;
        }

        $content = File::get($providerPath);

        // Add use statements
        $usePattern = "use App\\\Services";
        $useStatements = "use App\Interfaces\\{$name}\I{$name}Repository;\nuse App\Interfaces\\{$name}\I{$name}Services;\nuse App\Repository\\{$name}\\{$name}Repository;\nuse App\Services\\{$name}\\{$name}Services;";

        if (!Str::contains($content, $useStatements)) {
            $content = Str::replaceFirst($usePattern, $useStatements . "\n" . $usePattern, $content);
        }

        // Add bindings
        $bindPattern = "public function register\(\): void\s*\{\s*";
        $repositoryBinding = "        \$this->app->bind(I{$name}Repository::class, {$name}Repository::class);";
        $serviceBinding = "        \$this->app->bind(I{$name}Services::class, {$name}Services::class);";

        if (!Str::contains($content, $repositoryBinding)) {
            // Find the position after the register function opening brace
            $registerPos = strpos($content, 'public function register(): void');
            $bracePos = strpos($content, '{', $registerPos);
            $insertPos = strpos($content, "\n", $bracePos) + 1;

            $content = substr_replace($content, $repositoryBinding . "\n" . $serviceBinding . "\n", $insertPos, 0);
        }

        File::put($providerPath, $content);
        $this->info("Updated service provider");
    }
}
