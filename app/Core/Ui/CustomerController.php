<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CustomerController
{
    private const CUSTOMERS_PATH = '/ui/customers';

    public function __construct(private CustomerManagement $customers)
    {
    }

    public function create(Request $request): Response
    {
        try {
            $code = $this->customers->create($this->input($request), $this->actor($request), $this->correlationId($request));

            return new RedirectResponse(self::CUSTOMERS_PATH . '/' . rawurlencode($code) . '?saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | CustomerConflict $exception) {
            return $this->error(self::CUSTOMERS_PATH, $exception->getMessage());
        } catch (Throwable) {
            return $this->error(self::CUSTOMERS_PATH, 'Impossibile creare il cliente.');
        }
    }

    public function update(Request $request): Response
    {
        $code = $request->attributes->getString('customerId');
        try {
            $this->customers->update(
                $code,
                $request->request->getInt('version'),
                $this->input($request),
                $this->actor($request),
                $this->correlationId($request),
            );

            return $this->saved($code);
        } catch (InvalidArgumentException | CustomerConflict $exception) {
            return $this->error($this->customerPath($code), $exception->getMessage());
        } catch (Throwable) {
            return $this->error($this->customerPath($code), 'Impossibile aggiornare il cliente.');
        }
    }

    public function archive(Request $request): Response
    {
        $code = $request->attributes->getString('customerId');
        try {
            $this->customers->archive(
                $code,
                $request->request->getInt('version'),
                $this->actor($request),
                $this->correlationId($request),
            );

            return $this->saved($code);
        } catch (InvalidArgumentException | CustomerConflict $exception) {
            return $this->error($this->customerPath($code), $exception->getMessage());
        } catch (Throwable) {
            return $this->error($this->customerPath($code), 'Impossibile archiviare il cliente.');
        }
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        return [
            'customer_code' => $request->request->getString('customer_code'),
            'status' => $request->request->getString('status', 'active'),
            'customer_type' => $request->request->getString('customer_type'),
            'display_name' => $request->request->getString('display_name'),
            'first_name' => $request->request->getString('first_name'),
            'last_name' => $request->request->getString('last_name'),
            'company_name' => $request->request->getString('company_name'),
            'email' => $request->request->getString('email'),
            'phone' => $request->request->getString('phone'),
            'tax_identifier' => $request->request->getString('tax_identifier'),
            'vat_number' => $request->request->getString('vat_number'),
            'locale' => $request->request->getString('locale', 'it-IT'),
        ];
    }

    private function actor(Request $request): UserIdentity
    {
        $actor = $request->attributes->get('current_user');
        if (!$actor instanceof UserIdentity) {
            throw new InvalidArgumentException('Attore autenticato non disponibile.');
        }

        return $actor;
    }

    private function correlationId(Request $request): string
    {
        return $request->attributes->getString('correlation_id');
    }

    private function saved(string $code): RedirectResponse
    {
        return new RedirectResponse($this->customerPath($code) . '?saved=1', Response::HTTP_SEE_OTHER);
    }

    private function customerPath(string $code): string
    {
        return self::CUSTOMERS_PATH . '/' . rawurlencode($code);
    }

    private function error(string $path, string $message): RedirectResponse
    {
        return new RedirectResponse($path . '?error=' . rawurlencode($message), Response::HTTP_SEE_OTHER);
    }
}
