# When to Mock

Mock at **system boundaries** only:

- Provider APIs (Telna, Bondio)
- External services (Brevo for email, Stripe for payments)
- Firestore (prefer the emulator over mocking)
- Time/randomness

Don't mock:

- Your own classes/modules
- Internal collaborators
- Anything you control

## Designing for Mockability

At system boundaries, design interfaces that are easy to mock:

**1. Use dependency injection**

Pass external dependencies in rather than creating them internally:

```typescript
// Easy to mock
function assignEsim(booking: Booking, providerClient: ProviderClient) {
  return providerClient.provision(booking.packageSpecs);
}

// Hard to mock
function assignEsim(booking: Booking) {
  const client = new TelnaClient(process.env.TELNA_API_KEY);
  return client.provision(booking.packageSpecs);
}
```

**2. Prefer SDK-style interfaces over generic fetchers**

Create specific functions for each external operation instead of one generic function with conditional logic:

```typescript
// GOOD: Each function is independently mockable
const telnaClient = {
  getEsim: (iccid: string) => fetch(`/esims/${iccid}`),
  getPackages: (iccid: string) => fetch(`/esims/${iccid}/packages`),
  activatePackage: (iccid: string, packageId: string) =>
    fetch(`/esims/${iccid}/packages/${packageId}/activate`, { method: 'POST' }),
};

// BAD: Mocking requires conditional logic inside the mock
const telnaClient = {
  request: (endpoint: string, options?: RequestInit) => fetch(endpoint, options),
};
```

The SDK approach means:
- Each mock returns one specific shape
- No conditional logic in test setup
- Easier to see which endpoints a test exercises
- Type safety per endpoint
