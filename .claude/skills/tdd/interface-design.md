# Interface Design for Testability

Good interfaces make testing natural:

1. **Accept dependencies, don't create them**

   ```typescript
   // Testable
   function confirmBooking(booking: Booking, providerClient: ProviderClient) {}

   // Hard to test
   function confirmBooking(booking: Booking) {
     const client = new TelnaClient(process.env.TELNA_API_KEY);
   }
   ```

2. **Return results, don't produce side effects**

   ```typescript
   // Testable
   function resolvePackagePrice(bundle: DestinationBundle, partner: Partner): number {}

   // Hard to test
   function applyPartnerPricing(bundle: DestinationBundle, partner: Partner): void {
     bundle.b2b_price = computePrice(partner.financial_properties);
   }
   ```

3. **Small surface area**
   - Fewer methods = fewer tests needed
   - Fewer params = simpler test setup
