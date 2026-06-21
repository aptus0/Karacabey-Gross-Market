type QuantitySelectorProps = {
  name?: string;
  defaultValue?: number;
};

export function QuantitySelector({ name = "quantity", defaultValue = 1 }: QuantitySelectorProps) {
  return (
    <label className="quantity-selector">
      <span>Adet</span>
      <input name={name} type="number" min={1} max={99} defaultValue={defaultValue} />
    </label>
  );
}
