# Good and Bad Tests

## Good Tests

**Integration-style**: Test through real interfaces, not mocks of internal parts.

```typescript
// GOOD: Tests observable behavior
test("confirmed booking results in an assigned eSIM", async () => {
  const booking = await createBooking({ partner, packageSpecs });
  const result = await confirmBooking(booking.id);
  expect(result.status).toBe("CONFIRMED");
  expect(result.esims).toHaveLength(packageSpecs.length);
});
```

Characteristics:

- Tests behavior users/callers care about
- Uses public API only
- Survives internal refactors
- Describes WHAT, not HOW
- One logical assertion per test

## Bad Tests

**Implementation-detail tests**: Coupled to internal structure.

```typescript
// BAD: Tests implementation details
test("confirmBooking calls telnaClient.provision", async () => {
  const mockTelna = jest.mock(telnaClient);
  await confirmBooking(booking.id);
  expect(mockTelna.provision).toHaveBeenCalledWith(booking.packageSpecs[0]);
});
```

Red flags:

- Mocking internal collaborators
- Testing private methods
- Asserting on call counts/order
- Test breaks when refactoring without behavior change
- Test name describes HOW not WHAT
- Verifying through external means instead of interface

```typescript
// BAD: Bypasses interface to verify
test("createBooking writes to Firestore", async () => {
  await createBooking({ partner, packageSpecs });
  const doc = await db.collection("bookings").where("partner", "==", partner.id).get();
  expect(doc.empty).toBe(false);
});

// GOOD: Verifies through interface
test("createBooking makes booking retrievable", async () => {
  const booking = await createBooking({ partner, packageSpecs });
  const retrieved = await getBooking(booking.id);
  expect(retrieved.partner).toBe(partner.id);
});
```
